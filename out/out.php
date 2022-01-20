<?php

require_once(__DIR__ . '/../utils/chrony.php');

class sntp_report {
    
    const displim = 12;
    const dispsince = 1575326747;

private static function htsnip($t, $a) {
    
    $v = $a[$t];
    $a = abs($v);
    if      ($v > 0) $s = '+';
    else if ($v < 0) $s = '-';
    else	     $s = '';
    
    $ht = '<tr>';
    
    if ($t === 'coffset') {
	$ht .= '<td>';
	$ht .= str_pad(sprintf('%0.6f', $a), 9, ' ', STR_PAD_LEFT );
	$ht .= '</td>';
	$ht .= '<td class="sign">';
	$ht .= $s;
	$ht .= '</td>';
	$ht .= '<td>';
	$ht .= 'clock offset / error</td>';
    }
    
    if ($t === 'outd' || $t === 'ind') {
	$ht .= '<td>';
	$ht .= sprintf('%0.4f', $a);
	$ht .= '</td>';
	$ht .= '<td class="sign">';
	$ht .= $s;
	$ht .= '</td>';
	$ht .= '<td>';
	$ht .= $t === 'outd' ? 'outgoing delay' : 'incoming delay';
	$ht .= '</td>';
    }
    
    $ht .= '</tr>' . "\n";
    
    return $ht;
}
   
public static function sum($data, $dao) {
    $hht  = '<table>' . "\n";
    
    $sum = 0;
    $i = 0;
    
    $hht .= '<tr><th>cld</th><th>outd</th><th>ind</th><th>fam</th><th>host</th><th>st</th><th colspan="2"></th>' . "\n";
    
    $ht = '';
    
    foreach($data as $dat) {

	$r = $dat['calcs'];

	$off  = $dat['calcs']['coffset'];
	$sum += $off; $i++;
	$doff = sprintf('%0.3f', $off * 1000);
	$out  = sprintf('%0.3f', $r['outd'] * 1000);
	$in   = sprintf('%0.3f', $r['ind' ] * 1000);
	$fam = substr($dat['name'], 0, 4);
	$disp = $dat['disp'];
	$host = $dat['host'];
	
	if (!$disp) $dhost = '';
	else if ($disp < 0) $dhost = substr($host, strlen($host) - 4);
	else $dhost = substr($host, 0, $disp);
	
	$st   = $dat['parsed']['stratum'];
	$date = date('H:i:s m/d', $dat['based']['base']);
	
	$ht .= "<tr><td class='tar td1'>$doff</td><td class='tar pl'>$out</td><td class='tar pl'>$in</td><td>$fam</td>";
	$ht .= "<td class='host'>$dhost</td><td>$st</td><td>$date</td><td>$dat[seq]</td></tr>\n";
    }
    $ht .= '</table>' . "\n";
    
	if ($i > 0) $avg  = ($sum / $i);
	else	    $avg  = $sum;
    $davg = sprintf('%0.5f', $avg);
    
    $avgdb = $dao->getAvg(self::displim, self::dispsince);
    if (isset($avgdb[1])) {
		$ad = abs($avgdb[1]['sum'] - $sum);
        kwas( $ad < 0.0000003 ,'db and loc calc do not match');
	}
    
    $aht = self::getAvgHT($avgdb);
   
    $rht = $aht . $hht .  $ht;
    return $rht;
}

private static function getAvgHT($din) {
    $ht  = '<table>' . "\n";
    $ht .= '<tr><th>avg d</th><th>cnt</th><th>end</th><th>begin</th></tr>' . "\n";
    
    foreach($din as $row) {
        $ht .= '<tr>';
	
	$off  = $row['sum'] / $row['count'];
	$offd = sprintf('%0.3f', $off * 1000);
	
	$ed =  $date = date('H:i:s m/d/Y', $row['maxd']);
	$bd =  $date = date('H:i:s m/d/Y', $row['mind']);	
	$ht .= "<td class='tar td1'>$offd</td><td>$row[count]</td><td>$ed</td><td>$bd</td>";
	$ht .= '</tr>' . "\n";
	
	$x = 2;
    }
	
	$ht .= '</table>' . "\n";
    
    return $ht;
}

private static function minfo() {
    
    $ht = '';
    
    $temp = chrony_parse::get();
    $c = array_merge($temp['basic'], $temp['kwsntp']); unset($temp);
    if ($c) $s = $c['std'] . ', last poll at ' . $c['rr'];
    else $s = '';
    
    $ht .= '<table style="margin-bottom: 1ex"><caption style="text-align: left; font-weight: bold">&nbsp;&nbsp;chrony</caption>' . "\n";
    $ht .= "<tr><td>$c[std]</td></tr>\n";
    
    $ht .= "<tr><td>&nbsp;$c[nr] - server time</td></tr>\n";
    $ht .= "<tr><td>&nbsp;$c[rr] - last sync time</td></tr>\n";
    $ui  = $c['Update interval'];
    $ui  = preg_replace('/[a-zA-Z\s]/', '', $ui);
    $ui = round(trim($ui));
    $ht .= "<tr><td>&nbsp;$ui update interval</td></tr>\n";
    $ht .= "</table>\n";
    
    return $ht;
}


public static function report($dao) {

    $ht = '';
    
    $miht = '';
    $miht .= self::minfo();

    $data = $dao->getDat(self::displim, strtotime('Mon 2019-12-02 17:45:53 EST'));
    
    $sht = self::sum($data, $dao);
    
    $ht .= '<div class="fparent">' . "\n";
    
    foreach($data as $dat) {

	$r = $dat['calcs'];
	$st = $dat['parsed']['stratum'];
	

	$ht .= '<div class="fchild">' . "\n";
	$ht .= '<table style="font-family: monospace" class="r1">' . "\n";
	$ht .= '<tr><td>&nbsp;&nbsp;12345678</td><td></td><td>decimal places</td></tr>' ."\n";
	$ht .= self::htsnip('coffset', $r);
	$ht .= self::htsnip('outd', $r);
	$ht .= self::htsnip('ind', $r);

	$ht .= '<tr><td>' . sprintf('%0.8f', $r['srvd']) . "</td><td></td><td>server   delay</td></tr>\n";

	$ht .= "<tr><td>$st stratum</td><td></td><td colspan='1'>$dat[host] $dat[name]</td></tr>\n";
	$ht .= "<tr><td colspan='2'>$dat[seq]</td><td>seq</td></tr>\n";
	$ht .= '</table>' . "\n";

	$ht .= self::based($dat);
	$ht .= '</div>' . "\n";

    }

    $ht .= '</div>' . "\n";
    echo $miht . $sht . $ht;
    
} 

private static function based($dat) {
    $ht  = '<table style="margin-top: 1ex" class="based"> ' . "\n";
    
    $base = $dat['based']['base'];
    $ba   = $dat['based'];
    $avg  = ($ba['ls'] + $ba['lr']) / 2;
    
    $date = date('H:i:s m/d/Y', $base);
    
    $ht .= "<tr><td>$base ($date)</td></tr>" . "\n";
    $ht .= "<tr><td>$ba[ls]</td></tr>" . "\n";
    $ht .= "<tr><td>$ba[rr]</td></tr>" . "\n";
    $ht .= "<tr><td>$avg </td></tr>" . "\n";
    $ht .= "<tr><td>$ba[rs]</td></tr>" . "\n";
    $ht .= "<tr><td>$ba[lr]</td></tr>" . "\n";    
    $ht .= '</table>' . "\n";
    
    return $ht;
}

} // class
