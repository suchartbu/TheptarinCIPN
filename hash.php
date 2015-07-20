<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$algo = 'md5';
$filename = '11720-CIPN-5800010-20150517213204.txt';
echo hash_file($algo, $filename);