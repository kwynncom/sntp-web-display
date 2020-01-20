<?php

require_once('utils/kwutils.php');
require_once('utils/getServers.php');
require_once('utils/sntp.php');
require_once('out/out.php');
require_once('utils/testMode.php');

doit();

function doit() {

try {
    $dao   = new dao_sntp(); // data access object - get from database
    
    if (!sntp_testMode()) { // if we're in test mode, don't do a "get" / "ping" / NTP request; just move on to output from previous requests
	
	$hosti = ntpServerPool::get($dao); // get host info from the server pool
	$dat = fra_kw_sntp::doit($hosti['host']); // do the request
	$hosti['status'] = 'OK'; // if we didn't throw an exception, it worked
	$dat['disp'] = $hosti['disp'];
	$dao->put($dat, $hosti); unset($hosti); // save the data
    }
    } catch (Exception $ex) {
	$msg = $ex->getMessage();
	if (isset($hosti['host'])) {
	    $msg .= " host $hosti[host]";
	    $dao->kod($hosti); // upon failure of a given host, register "Kiss of Death" as specified in SNTP protocol
	}
    }
    
    require_once('out/template.php'); // HTML / text output
}
