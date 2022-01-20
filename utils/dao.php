<?php

require_once('/opt/kwynn/kwutils.php');

class dao_sntp extends dao_generic {
    
    const dbName = 'sntp';
    const minTime = 1570057086;
    
    public function __construct() {
	parent::__construct(self::dbName);
	$this->servcoll  = $this->client->selectCollection(self::dbName, 'servers');
	$this->uheadcoll = $this->client->selectCollection(self::dbName, 'usageh');
	$this->ulicoll   = $this->client->selectCollection(self::dbName, 'usageli');
	$this->rcoll     = $this->client->selectCollection(self::dbName, 'dat'); // rcoll as in raw or result
    }
    
    public function getDat($limit = 12, $since = self::minTime) {
	return $this->rcoll->find(['based.base' => ['$gte' => $since]], ['sort' => ['seq' => -1], 'limit' => $limit])->toArray();
    }
    
    public function put($din, $hi) {
	$this->upHeader($hi);
	$dat = $din;
	$dat['name'] = $hi['name'];
	$dat['host'] = $hi['host'];
	$dat['seq']  = $hi['seq'];
	$this->rcoll->insertOne($dat);
    }
    
    public function init($arr) {
	
	if ($this->uheadcoll->count([]) > 0) return false;
	
	$nowu = microtime(1);
	foreach($arr as $key => $item) {
	    $item['name'] = $key;
	    $this->servcoll->upsert(['name' => $key], $item);
	    foreach($item['hosts'] as $host) {
		$hinfo['name'] = $item['name'];
		$hinfo['host'] = $host;
		$hinfo['status'] = 'init';
		$hinfo['waituntil'] = $nowu;
		$hinfo['from'] = $item['from'];
		$hinfo['minpoll'] = isset($item['minpoll']) ?
			                  $item['minpoll'] : 67;
		$hinfo['kod'] = 0;
		$hinfo['disp'] = $item['disp'];
		$this->uheadcoll->insertOne($hinfo);
	    }
	}
    }
    
    private function isOKGlobalLimits() {
	static $lims = false;
	static $now  = false;
	
	if (!$lims)  $lims =					[ 60 =>  6, 3600 =>  20, 86400 =>  60];
	// if (!$lims && !is****AWS())  $lims = [ 60 => 20, 3600 => 100, 86400 => 150];
	
	if (!$now) $now = time();
	
	foreach($lims as $per => $ccnt) {
	    $c = $this->ulicoll->count(['ts' => ['$gte' => $now - $per]]);
	    if ($c > $ccnt) return 0;
	}
	
	return 1;
    }
    
    public function get($loc) {	

	/* if (time() < strtotime('2019-11-26 20:59') && !is***AWS()) 
	    return $this->uheadcoll->findOne(['host' => '[2610:20:6f15:15::26]']); */
	
	if ($this->uheadcoll->count() === 0) return 0;
	
	kwas($this->isOKGlobalLimits(), 'failed global quota');

	$lq = ['$or' => [['from' => $loc], ['from' => 'anywhere']]];
	$q  = ['$and' => [$lq, ['waituntil' => ['$lt' => microtime(1)]] ]];
	$ha = $this->uheadcoll->findOne($q, ['sort' => ['ts' => 1]]); 
	if (!$ha) throw new Exception('no host found - probably over quota');
	
	$ue = $ha;
	
	$ue['seq'] = $this->getSeq('ntpCalls');
	$ue['status'] = 'pre';
	if (       isset($ha['minpoll']))
	$ue['minpoll'] = $ha['minpoll'];
	
	$this->upHeader($ue);

	return $ue;
    }
    
    public function kod($dat) {
	$dat['status'] = 'kod';
	$this->upHeader($dat);
    }
    
    public function upHeader($dat) {
	$ue = $dat; // usage event
	$nowu = round(microtime(1), 6);
	$now  = intval(round($nowu));
	$ue['tsu'] = $nowu;
	$ue['ts'] = $now;
	$ue['r']  = date('r', $now);
	
	$wu = $nowu + $dat['minpoll'];
	
	if ($dat['status'] === 'kod') {
	    if (!isset($ue['kod'])) $ue['kod'] = 1;
	    else		     $ue['kod']++;	
	    
	    $ue['waituntil'] = $now + 2013 * $ue['kod'];
	} else {
	    $ue['waituntil'] = $wu;
	    if ($dat['status'] === 'OK') $ue['kod'] = 0;
	}
	
	$this->uheadcoll->upsert(['host' => $ue['host']], $ue);
	$this->uheadcoll->updateMany(['name' => $ue['name'], 'kod'  => 0], ['$set' => ['waituntil' => $wu]]); // update the whole "family" of hosts
	unset($ue['_id']); // I'm not sure this matters, but it avoids a potential unique ID conflict
	$this->ulicoll->upsert(['seq' => $ue['seq']], $ue);
    }
    
    public function getAvg($limit, $since) {
	
	$match = ['$match' => ['based.base' => ['$gte' => $since]]];
	$sort  = ['$sort'  => [ 'seq' => -1 ] ];
	$limit = ['$limit' => $limit];
	$group = ['$group' => [
		    '_id'   =>  "aggdat",
		    'count' => ['$sum' =>  1],
		    'sum'   => ['$sum' => '$calcs.coffset'],
		    'mind'  => ['$min' => '$based.base' ],
		    'maxd'  => ['$max' => '$based.base' ]
		    ]
		];
	
	$q = [ $match, $sort, $limit, $group];
	$ra = $this->rcoll->aggregate($q)->toArray();
	$aa = $this->rcoll->aggregate([$group])->toArray();
	
	$r = [];
	if (isset($aa[0]))
	$r[] =    $aa[0];
	if (isset($ra[0]))
	$r[] =    $ra[0];
		
	return $r;
	
    }
}
