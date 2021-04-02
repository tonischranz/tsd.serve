<?php
const SERVE_BASE = 'https://github.com/tonischranz';
const SERVE_REPO = 'tsd.serve';
const SERVE_BRANCH = 'next';

$clean_url= SERVE_BASE . '/' . SERVE_REPO . '/raw/' . SERVE_BRANCH .'/clean.php';
header('Content-Disposition: attachment; filename="clean.php"');

readfile($clean_url);