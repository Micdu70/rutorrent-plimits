<?php

require_once(dirname(__FILE__).'/limits.php');

$tl = new trackersLimit();
if($tl->init())
	$theSettings->registerPlugin($plugin["name"],$pInfo["perms"]);