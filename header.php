<?php

define('VERSION', '0.1');

include($config['dir']['absolut'].'/functions.php');

try {
	$db = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], 
					array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
} catch (PDOException $e) {
    exit('Database connexion fail : ' . $e->getMessage());
}

//							CODE			LOCALE (locale -a)
$langueEtLocalDispo=array(	'fr'		=> 'fr_FR', 
							'en'		=> 'en_US',
							);

// Dans les URL on utilisera les codes langues https://support.crowdin.com/api/language-codes/
// On a une fonction pour retrouve le local à partir (et vis et versa)

/* Language */
if(php_sapi_name() != 'cli') {
    if (isset($_GET['langueChange'])) {
        $locale = lang2locale($_GET['langueChange']);
    	$localeshort=locale2lang($locale);
        setcookie("langue",$localeshort,strtotime( '+1 year' ), '/');
    } else {
        if (isset($_COOKIE['langue'])) {
            $locale = lang2locale($_COOKIE['langue']);
            $localeshort=locale2lang($locale);
        } else {
            $HTTP_ACCEPT_LANGUAGE=$_SERVER['HTTP_ACCEPT_LANGUAGE'];
            //echo $HTTP_ACCEPT_LANGUAGE.'<br />';
            $lang_from_http_accept = explode(',', $HTTP_ACCEPT_LANGUAGE);
            //echo $lang_from_http_accept[0].'<br />';
            $locale = lang2locale($lang_from_http_accept[0]);
            if (substr($locale,0,2) != substr($lang_from_http_accept[0],0,2)) {
                //echo "Non trouvé, 2ème tentative";
                $lang_from_http_accept = explode('-', $lang_from_http_accept[0]);
                //echo $lang_from_http_accept[0].'<br />';
                $locale = lang2locale($lang_from_http_accept[0]);
            }
            //echo $locale.'<br />';
            $localeshort=locale2lang($locale);
        }
    }

    // Définition de la langue :
    $results=putenv("LC_ALL=$locale.utf8");
    if (!$results) {
        exit ('putenv failed');
    }
    $results=putenv("LC_LANG=$locale.utf8");
    if (!$results) {
        exit ('putenv failed');
    }
    $results=putenv("LC_LANGUAGE=$locale.utf8");
    if (!$results) {
        exit ('putenv failed');
    }
    $results=setlocale(LC_ALL, "$locale.utf8");
    if (!$results) {
        exit ('setlocale failed: locale function is not available on this platform, or the given local does not exist in this environment');
    }
    bindtextdomain("messages", "./lang");
    textdomain("messages");
    /* / language */
}


if (!is_writable($config['dir']['absolut'].'/'.$config['dir']['archive'])) {
    exit(_('The directory '.$config['dir']['archive'].' is not accessible in writing, please report it to the administrator'));
}

?>
