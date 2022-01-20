<?php

require_once('/opt/kwynn/kwutils.php');

class fra_kw_sntp {
    
const bit_max       = 4294967296;
const epoch_convert = 2208988800;

public static function doit($server) {
    $ps   = self::getPacketAndSocket($server);
    $cres = self::docrit($ps); unset($ps); // crit as in the critical, time-sensitive processing -- the NTP call itself for one.
    $pres = self::parseNTPResponse($cres['r']); unset($cres['r']);
    $ma = array_merge($cres, $pres);
    $sres = self::sharpen($ma);
    $calca = self::calcs($sres);
    return ['calcs' => $calca, 'parsed' => $pres, 'based' => $sres, 'local' => $cres];
}

// private static function 

private static function getPacketAndSocket($server) {
    
    kwas($server, 'false / null host sent - should not get here!');

    $header = '00';
    $header .= sprintf('%03d',decbin(3)); // 3 indicates client
    $header .= '011';
    $request_packet = chr(bindec($header)); unset($header);
    for ($j=1; $j < 40; $j++) $request_packet .= chr(0x0);
    set_error_handler('kw_error_handler', E_ALL - E_WARNING);
    $socket = @fsockopen('udp://'. $server, 123, $err_no, $err_str); 
    kwas($socket, 'cannot open connection to ' . $server);
    set_error_handler('kw_error_handler', E_ALL);
    return ['pack' => $request_packet, 'sock' => $socket];
}
    
private static function docrit($packsock) {
    $socket = $packsock['sock'];
    $request_packet = $packsock['pack'];
    $expectedLen = 48;
    stream_set_timeout($socket, 1);
    
    $lss = microtime();  // time-critical starts here, so do what you can before it
    $local_sent_explode = explode(' ', $lss);
    $originate_seconds = $local_sent_explode[1] + self::epoch_convert;
    $originate_fractional = round($local_sent_explode[0] * self::bit_max);
    $originate_fractional = sprintf('%010d',$originate_fractional);
    $packed_seconds = pack('N', $originate_seconds);
    $packed_fractional = pack("N", $originate_fractional);
    $request_packet .= $packed_seconds;
    $request_packet .= $packed_fractional;
    if (!fwrite($socket, $request_packet)) throw new Exception ('bad socket write');
    $response = fread($socket, $expectedLen);
    $lrs = microtime();
    fclose($socket);
    $rlen = strlen($response);
    kwas($rlen === $expectedLen, 'bad SNTP server result: got length of ' . "$rlen rather than $expectedLen");
    return ['r' => $response, 'lrs' => $lrs, 'lss' => $lss];
}

private static function parseNTPResponse($response) {

    $unpack0 = unpack("N12", $response);
    
    $r['rrs'] = sprintf('%u', $unpack0[ 9]) - self::epoch_convert; // remote receive packet second-precision ts
    $r['rrf'] = sprintf('%u', $unpack0[10]) / self::bit_max;       // remote receive packet fractional time
    $r['rss'] = sprintf('%u', $unpack0[11]) - self::epoch_convert; // remote sent ...
    $r['rsf'] = sprintf('%u', $unpack0[12]) / self::bit_max;       // ...
    $stratum  = self::getStratum($response); kwas($stratum && intval($stratum) >= 1, 'SNTP Kiss of Death (KOD)');
    $r['stratum'] = $stratum;
    return $r;
}

private static function getStratum($response) {
    $unpack1 = unpack("C12", $response);
    $stratum_response =  base_convert($unpack1[2], 10, 2);
    $stratum_response = bindec($stratum_response);
    return $stratum_response;
}

private static function sharpen($n) {
    
    $lsa = self::mtstoa($n['lss']); unset($n['lss']); // local sent time array as in exploded and typed microtime()
    $lra = self::mtstoa($n['lrs']); unset($n['lrs']); // local received time
    
    $ia = [$n['rrs'], $n['rss'], $lsa[0], $lra[0]];
    
    $min = min($ia); self::veryRecentTSOrDie($min);
    
    $ra['base'] = $min;
    $ra['ls'  ] = ($lsa[0]   - $min) + $lsa[1]; // local sent time, high-precision
    $ra['lr'  ] = ($lra[0]   - $min) + $lra[1]; // local received
    $ra['rr'  ] = ($n['rrs'] - $min) + $n['rrf']; // remote received
    $ra['rs'  ] = ($n['rss'] - $min) + $n['rsf']; // remote sent
    $ra['stratum'] = $n['stratum'];
    
    return $ra;
}

public static function mtstoa($sin) { // microtime-stamp to array
    $a1 = explode(' ', $sin);
    $i  = intval($a1[1]); self::veryRecentTSOrDie($i);
    $f  = floatval($a1[0]);
    $ar[0] = $i;
    $ar[1] = $f;
    $ar[2] = $i + $f;
    return $ar;
}

public static function veryRecentTSOrDie($iin) {
    static $now = false;
    if ($now === false) $now = time();
    kwas(!(abs($iin - $now) > 20 && isAWS()), 'timestamps too far off');
}

private static function calcs($r) {
    $re['coffset'] = -(round((($r['rr'] - $r['ls']) + ($r['rs'] - $r['lr'])) / 2, 6)); // I am using opposite sign of official
    $re['srvd'   ] = $r['rs'] - $r['rr'];
    $re['outd'   ] = round(($r['rr'] - $r['ls']), 6);
    $re['ind'    ] = round(($r['lr'] - $r['rs']), 6);
    
    return $re;
}
}
