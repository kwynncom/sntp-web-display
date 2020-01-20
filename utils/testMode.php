<?php

function sntp_testMode() {
    $tl = time() < strtotime('2019-11-20 23:59');
    if ($tl && isKwDev()) return true;
    return false;
}