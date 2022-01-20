<?php

require_once('utils/dao.php');

class ntpServerPool {
    
    private $dao = false;
    
    public static function get($dao) {
	
	$myloc = 'VA';
	// $myloc = 'GA';
	
	$host = $dao->get($myloc);
	if ($host === 0) {
	    self::popmyls($dao);
	    $host = $dao->get($myloc);
	}
	
	return $host;
    }
   
    private static function popmyls($dao) {
	
	$nist4 = [
	    	'129.6.15.26',
		'129.6.15.27',
		'129.6.15.28',
		'129.6.15.29',
		'129.6.15.30',
	];	
	
	$nist6 = [
		'[2610:20:6f15:15::26]',
    		'[2610:20:6f15:15::27]'	    
	];
	
	// if (!is****AWS()) 
		$nisths = array_merge($nist4, $nist6);
	// else          $nisths = $nist6;
	
	$a['NIST'] = [
	    'minpoll' => 4,
	    'hosts' => $nisths,
	    'loc' => 'Gaithersburg, MD, US',
	    'info' => 'https://tf.nist.gov/tf-cgi/servers.cgi',
	    'from' => 'anywhere',
	    'disp' => -4
	];
	
	$a['AWS-internal'] = [
	    'hosts' => ['169.254.169.123'],
	    'loc' => 'northern VA, US',
	    'info' => 'https://aws.amazon.com/blogs/aws/keeping-time-with-amazon-time-sync-service/',
	    'from' => 'VA',
	    'disp' => false
	    
	];
	
	$a['USG'] = [
	  'hosts' => ['rolex.usg.edu'],
	  'from' => 'GA', 
	    'disp' => false
	];
	
	$a['VATech'] = [
	    'hosts' => [
		'ntp-1.vt.edu',
		'ntp-2.vt.edu',
		'ntp-3.vt.edu',
		'ntp-4.vt.edu'
	    ],
	    'from' => 'VA',
	    'disp' => 5
	];
	
	$dao->init($a);
    }
}