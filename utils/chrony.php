<?php // note this is part of sntp and sun / sun/snyc - harmonized 2020/01/07 00:32am

require_once('/opt/kwynn/kwutils.php');

if (isset($_REQUEST['getOffsetOnly'])) {
    $r = chrony_parse::get();
    if (isset($r['std'])) {
	echo $r['std'];
	exit(0);
    }
    
    http_response_code(503);
    exit(503);

}

class chrony_parse {

public static function get() {
    
    try {

    $a = self::getInternal();
    $ret = [];
    $ret['raw'] = $a;
        
    $r = $a['Reference ID'];
    preg_match('/([0-9A-Z]+) \(([^\)]+)\)/', $r, $matches);
    kwas(isset($matches[2]) && $matches[2] && is_string($matches[2]) && strlen(trim($matches[2])) > 5, 'rID regex fail');
    
    $sn = [];
    $sn['rid'] = $matches[2];
    
    if (isAWS() && $sn['rid'] === '169.254.169.123') $sn['rname'] = 'AWS EC2 Time Sync Service';
    else $sn['rname'] = '';
    
    $key = 'Ref time (UTC)';
    kwas(isset($a[$key]), 'no UTC ref time');
    
    $ts = strtotimeRecent($a[$key] . ' UTC');
    $sn['rts'] = $ts;
    
    $df = 'g:i:s A D m/j';
    $sn['rr']  = date($df, $ts); unset($ts);
    $sn['nr']  = date($df);
    
    $st = $a['System time'];
    
    preg_match('/(^\d+\.\d+) seconds (\w+) of NTP time/', $st, $matches); unset($st); kwas(isset($matches[2]), 'regex fail offset'); 
    
    $s = $matches[1];

    if      ($matches[2] === 'fast') $sign = '+';
    else if ($matches[2] === 'slow') $sign = '-';
    else kwas(0, 'not fast or slow');

    
    $fs = $sign . sprintf('%0.3f', $s * 1000000);
    
    $b = [];
    
    $b['stus'] = $fs;
    $b['stusr'] = round($fs);
    $b['std'] = $fs . '&#181;s';
    
    if ($sign === '-') $mult = -1;
    else               $mult =  1;
    
     $b['stms']   = $s   * 1000;
     $b['asOfms'] =         intval(round(microtime(1) * 1000, 0));
     $b['sts']    = ($mult >= 0 ? '+' : '-') . $s;
     $b['stats']  = time();
     
     $ret['basic'] = $b;
     $ret['status'] = 'OK';
     
     $key = 'Update interval';
     $sn[$key] = $a[$key];
     
     $ret['kwsntp'] = $sn;

     return $ret;
     
    }
    catch (Exception $ex) { 
	if (PHP_SAPI === 'cli') throw $ex;
	return false; 
    }
}

private static function getInternal() {
    $res = shell_exec('chronyc tracking');
    $anl = explode("\n", $res); unset($res);
    $linec = count($anl);
    foreach($anl as $row) {
	$ac = explode(' : ', $row);
	if (!$ac || count($ac) !== 2) continue;
	if (   trim($ac[0]) &&  trim($ac[1]))
	    $a[trim($ac[0])] =  trim($ac[1]);
    }
    
    kwas(count($anl) - 1 === count($a) && count($a) === 13, 'array fail 1');
    
    return $a;
}
}
