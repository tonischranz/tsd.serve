<?php
const SERVE_BASE = 'https://github.com/tonischranz';
const SERVE_REPO = 'tsd.serve';
const SERVE_BRANCH = 'next';
const CLEAN_FILE = 'clean.php';

$clean_url= SERVE_BASE . '/' . SERVE_REPO . '/raw/' . SERVE_BRANCH .'/' . CLEAN_FILE;
header('Content-Type: application/x-httpd-php');
header('Content-Disposition: attachment; filename="'. CLEAN_FILE . '"');

readfile($clean_url);