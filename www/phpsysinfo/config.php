<?php 
if (!defined('PSI_CONFIG_FILE')){
    /**
     * phpSysInfo version
     */
    define('PSI_VERSION','3.1.1');
    /**
     * phpSysInfo configuration
     */
    define('PSI_CONFIG_FILE', APP_ROOT.'/phpsysinfo.ini');

    /* default error handler */
    if (function_exists('errorHandlerPsi')) {
        restore_error_handler();
    }
    
    /* fatal errors only */
    $old_err_rep = error_reporting();
    error_reporting(E_ERROR);
    
    /* get git revision */ 
    if  (file_exists (APP_ROOT.'/.git/HEAD')){
        $contents = @file_get_contents(APP_ROOT.'/.git/HEAD');
        if ($contents && preg_match("/^ref:\s+(.*)\/([^\/\s]*)/m", $contents, $matches)) {
            $contents = @file_get_contents(APP_ROOT.'/.git/'.$matches[1]."/".$matches[2]);
            if ($contents && preg_match("/^([^\s]*)/m", $contents, $revision)) {
                define('PSI_VERSION_STRING', PSI_VERSION ."-".$matches[2]."-".$revision[1]);
            } else {
                define('PSI_VERSION_STRING', PSI_VERSION ."-".$matches[2]);
            }
        }
    }
    /* get svn revision */
    if ((!defined('PSI_VERSION_STRING'))&&(file_exists (APP_ROOT.'/.svn/entries'))){ 
        $contents = @file_get_contents(APP_ROOT.'/.svn/entries');
        if ($contents && preg_match("/dir\n(.+)/", $contents, $matches)) {
            define('PSI_VERSION_STRING', PSI_VERSION."-r".$matches[1]);
        } else {
            define('PSI_VERSION_STRING', PSI_VERSION);
        }
    }
    if (!defined('PSI_VERSION_STRING')){ 
        define('PSI_VERSION_STRING', PSI_VERSION);
    }

    /* get Linux code page */
    if (PHP_OS == 'Linux'){
        if  (file_exists ('/etc/sysconfig/i18n')){
            $contents = @file_get_contents('/etc/sysconfig/i18n');
        } else if  (file_exists ('/etc/default/locale')){
            $contents = @file_get_contents('/etc/default/locale');
        } else if  (file_exists ('/etc/locale.conf')){
            $contents = @file_get_contents('/etc/locale.conf');
        } else if  (file_exists ('/etc/sysconfig/language')){
            $contents = @file_get_contents('/etc/sysconfig/language');
        } else {
            $contents = false;
            if  (file_exists ('/system/build.prop')){ //Android
                define('PSI_SYSTEM_CODEPAGE', 'UTF-8');
            }           
        }
        if ($contents && ( preg_match('/^(LANG="?[^"\n]*"?)/m', $contents, $matches)
           || preg_match('/^RC_(LANG="?[^"\n]*"?)/m', $contents, $matches))) {
            if (@exec($matches[1].' locale -k LC_CTYPE 2>/dev/null', $lines)) {
                foreach ($lines as $line) {
                    if (preg_match('/^charmap="?([^"]*)/', $line, $matches2)) {
                        define('PSI_SYSTEM_CODEPAGE', $matches2[1]);
                        break;
                    }
                }
            }
            if (@exec($matches[1].' locale 2>/dev/null', $lines)) {
                foreach ($lines as $line) {
                    if (preg_match('/^LC_MESSAGES="?([^\."@]*)/', $line, $matches2)) {
                        $lang = "";
                        if (is_readable(APP_ROOT.'/data/languages.ini') && ($langdata = @parse_ini_file(APP_ROOT.'/data/languages.ini', true))){
                            if (isset($langdata['Linux']['_'.$matches2[1]])) {
                                $lang = $langdata['Linux']['_'.$matches2[1]];
                            }
                        }
                        if ($lang == ""){
                            $lang = 'Unknown';
                        }
                        define('PSI_SYSTEM_SYSLANG', $lang.' ('.$matches2[1].')');
                        break;
                    }
                }
            }

        }
    } else if (PHP_OS == 'Haiku'){
            if (@exec('locale -m 2>/dev/null', $lines)) {
                foreach ($lines as $line) {
                    if (preg_match('/^"?([^\."]*)\.?([^"]*)/', $line, $matches2)) {

                        if ( isset($matches2[2]) && !is_null($matches2[2]) && (trim($matches2[2]) != "") ){
                            define('PSI_SYSTEM_CODEPAGE', $matches2[2]);
                        }

                        $lang = "";
                        if (is_readable(APP_ROOT.'/data/languages.ini') && ($langdata = @parse_ini_file(APP_ROOT.'/data/languages.ini', true))){
                            if (isset($langdata['Linux']['_'.$matches2[1]])) {
                                $lang = $langdata['Linux']['_'.$matches2[1]];
                            }
                        }
                        if ($lang == ""){
                            $lang = 'Unknown';
                        }
                        define('PSI_SYSTEM_SYSLANG', $lang.' ('.$matches2[1].')');
                        break;
                    }
                }
            }
    }
    
    if (!defined('PSI_SYSTEM_SYSLANG')){ 
        define('PSI_SYSTEM_SYSLANG', null);
    }
    if (!defined('PSI_SYSTEM_CODEPAGE')){ 
        define('PSI_SYSTEM_CODEPAGE', null);
    }
    
    /* restore error level */
    error_reporting($old_err_rep);
    
    /* restore error handler */
    if (function_exists('errorHandlerPsi')) {
        set_error_handler('errorHandlerPsi');
    }

    define('ARRAY_EXP', '/^return array \([^;]*\);$/'); //array expression search

    if ((!is_readable(PSI_CONFIG_FILE)) || !($config = @parse_ini_file(PSI_CONFIG_FILE, true))){
        $tpl = new Template("/templates/html/error_config.html");
        echo $tpl->fetch();
        die();
    } else {
        foreach ($config as $name=>$group) {
            if (strtoupper($name)=="MAIN") {
                $name_prefix='PSI_';
            } else {
                $name_prefix='PSI_PLUGIN_'.strtoupper($name).'_';
            }
            foreach ($group as $param=>$value) {
                if ($value===""){
                    define($name_prefix.strtoupper($param), false);
                } else if ($value==1){
                    define($name_prefix.strtoupper($param), true);
                } else {
                    if (strstr($value, ',')) {
                        define($name_prefix.strtoupper($param), 'return '.var_export(preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY),1).';');
                    } else {
                        define($name_prefix.strtoupper($param), $value);
                    }
                }
            }
        }
    }
}
?>
