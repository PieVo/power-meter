#define _GNU_SOURCE
#define ROTATIONS_PER_KWH	375	/* Rotations per kWh */
#define INTERVAL		10	/* Minutes */
#define dbg(fmt, args...)  if (debug == 1) printf(fmt, ## args)
#define TOTAL		"/var/www/total"
#define TOTAL_HTML	"/var/www/total.html"
#define DETAIL		"/var/www/detail.csv"
#define DAILY		"/var/www/daily.csv"

#include <stdio.h>
#include <signal.h>
#include <unistd.h>
#include <time.h>
#include <signal.h>
#include <errno.h>

int debug = 0;
double watt_hour = 0;
float daily_watt_hour = 0;
float interval_power = 0;
static float one_rotation_wh;

void sig_handler(int signo)
{
	if (signo == SIGUSR1) {
		dbg(".");
		fflush(stdout);
		watt_hour += one_rotation_wh;
		interval_power += one_rotation_wh;
		daily_watt_hour += one_rotation_wh;
	}
}

void print_syntax() {
	printf("collector -p <preset value> -d\n");
}

int readfloatfromfile(const char *entry, double *value)
{
	FILE *fd = NULL;
	int ret = -1;

	fd = fopen(entry, "r");
	if (fd != NULL) {
		do {
			ret = fscanf(fd, "%lf", value);
			if (ret == EOF && errno == EINTR) {
					fseek(fd, 0, SEEK_SET);
					dbg("A signal aborted the read, retry\n");
			}
		} while (ret == EOF && errno == EINTR);
		fclose(fd);
	} else
		dbg("file not found\n");

	return ret;
}

int writestrtofile(const char *entry, const char *value, const char *mode)
{
	FILE *fd = NULL;
	int ret = -1;
	long position;

	fd = fopen(entry, mode);
	if (fd != NULL) {
		position = ftell(fd);
		/* Add a newline after each entry */
		if (position != 0)
			ret = fprintf(fd, "\n");
		do {
			ret = fprintf(fd, "%s", value);
			if (ret == EOF && errno == EINTR) {
				fseek(fd, position, SEEK_SET);
				dbg("A signal aborted the write, retry\n");
			}
		} while(ret == EOF && errno == EINTR);
		fclose(fd);

	} else
		dbg("file not found\n");

	return ret;
}

int main(int argc, char *argv[])
{
	time_t start, end;
	int i = 1;
	float tmp;
	char string[64];
	struct tm *loctime;
	int day = 0, oldday = 0;

	while (argc > i) {
		if (argv[i][0] == '-') {
			switch(argv[i][1]) {
			case 'd':
				debug = 1;
				dbg("Enable debugging\n");
				break;
			case 'p':
				watt_hour = (int)atoi(argv[i+1]);
				dbg("Preset total used power: %.1fW/h\n", watt_hour);
				i++;
				break;
			case 'r':
				daily_watt_hour = (int)atoi(argv[i+1]);
				dbg("Preset daily used power: %.1fW/h\n", daily_watt_hour);
				i++;
				break;
			default:
				break;
			}
			i++;
		} else {
			print_syntax();
			return -1;
		}
	}

	if (signal(SIGUSR1, sig_handler) == SIG_ERR) {
		printf("Failed to catch SIGUSR1\n");
		return -1;
	}

	if (watt_hour == 0) {
		if (readfloatfromfile(TOTAL, &watt_hour) < 0)
			printf("No total usage data available\n");
		else
			printf("Preset total data from file: %.1fW/h\n", watt_hour);
	}


	one_rotation_wh = 1000 / (float)ROTATIONS_PER_KWH;
	dbg("One rotation is %f Wh, interval is %d minutes\n", one_rotation_wh, INTERVAL);
	start = time(NULL);
	
	/* Prevent inital daily log to be written */
	loctime = localtime(&start);
	oldday = loctime->tm_mday;

	while(1) {
		sleep(1);
		end = time(NULL);
		if (difftime(end, start) >= (INTERVAL * 60)) {
			tmp = interval_power * (60 / INTERVAL);
			dbg("\nCurrent power (%dmin avg): %.1fW (%f)\n", INTERVAL, tmp, interval_power);
			dbg("Total used power: %.1fW/h\n", watt_hour);
			dbg("Daily used power: %.1fW/h\n", daily_watt_hour);

			/* Create a small html file with total value */
			sprintf(string, "<html><h2>Total: %.2fkWh</h2><html>", (watt_hour / 1000));
			writestrtofile(TOTAL_HTML, string, "w");

			/* Store total value in case of reboots etc */
			sprintf(string, "%.1f", watt_hour);
                        writestrtofile(TOTAL, string, "w");

			/* Highcharts requires milliseconds sinds epoc */	
			sprintf(string, "%lu000,%u", time(NULL), (unsigned int)tmp);
			writestrtofile(DETAIL, string, "a");

			interval_power = 0;
			start = time(NULL);

			/* Check for day change and store total */
			loctime = localtime(&start);
			day = loctime->tm_mday;
			if (day != oldday) {
				dbg("Writing (previous day) daily used power to file");
				sprintf(string, "%lu000,%.2f", (start - 3600), (daily_watt_hour / 1000));
				writestrtofile(DAILY, string, "a");
				daily_watt_hour = 0;
			}
			oldday = day;
			
		}
	}
	return 0;
}
