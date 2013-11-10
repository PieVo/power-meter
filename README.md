power-meter
===========

Scripts and application for monitoring analog power meter

The www folder contains html files for displaying the current, daily and total usage. Also a version of PHP sysinfo is included.

The configs folder contains config files for lighthttpd and for motion. Motion is a package that performs most of the work for this function and will certainly require tuning per different setup. The config file itself contains good documentation.

The collector folder contains a program that does the actual counting. I probably requires rebuilding because the number of rotations per kW is set a as a define.

See http://voorthuijsen.blogspot.nl/2013/03/monitoring-analog-power-meter.html for details.
