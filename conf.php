<?php

// === INFO ===
// This plugin will try to load "trackers.lst" file from 'share/users/<user>/settings/' directory first
// If file is not found then it will load "trackers.lst" from plugin directory

$MAX_UL_LIMIT 	= 1;		// 1 Kb/s
$MAX_DL_LIMIT 	= 0;		// unlimited

$RATIO_LIMIT	= 0;		// percents

$trackersCheckInterval	= 1;	// in minutes

$enableOnDHT = true;		// enable on DHT enabled torrents

$preventUpload	= true;

$log_debug = false;		// Log info to /tmp/errors.log
