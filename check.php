<?php

if( !chdir( dirname( __FILE__) ) )
	exit();

if( count( $argv ) > 1 )
	$_SERVER['REMOTE_USER'] = $argv[1];

require_once( "limits.php" );

$tl = new trackersLimit();
$tl->check();