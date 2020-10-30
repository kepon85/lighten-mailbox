<?php

define('VERSION', '0.1');

include($config['dir']['absolut'].'/functions.php');

try {
	$db = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], 
					array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
} catch (PDOException $e) {
    exit('Database connexion fail : ' . $e->getMessage());
}

?>
