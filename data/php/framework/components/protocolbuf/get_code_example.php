<?php
    if($argc < 2) die("usage:get_code_example.php example.proto");
    require_once('./parser/pb_parser.php');
    $test = new PBParser();
    for($i = 1; $i < $argc; $i++){
        $protopath = $argv[$i];
        $test->parse($protopath);
    }    
