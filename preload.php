<?php
$files = glob('tsd/serve/*.php');
foreach ($files as $f) {
    opcache_compile_file($f);
}
