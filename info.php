<?php

phpinfo();

/* $address = '127.0.0.1';
  $port = 9000;
  $sock = socket_create (AF_INET, SOCK_STREAM, 0);
  socket_bind ($sock, $address, $port) or die ('Unable to bind');
  socket_listen ($sock);
  $client = socket_accept ($sock);
  echo "connection established: $client";
  socket_close ($client);
  socket_close ($sock); */


/* echo 'Test RegEx<br />';
  $a = 'abcd';
  $s = "asdf\nqwer\nasdfghkl";
  $p = ['#(asdfgh)kl#' => function($m){
  return 'asdf';
  }, '#(asdf)#' => function($m){
  return 'ASDF';
  },
  '#(qw)(.*)#' => function($m) use ($a) {
  var_dump ($m);
  return $m[1] . $a;
  }];
  echo preg_replace_callback_array ($p, $s); */

var_dump($_SERVER);
