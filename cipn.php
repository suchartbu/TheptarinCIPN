<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of cipn
 * 1. สร้างรหัสเพื่อเตรียมค่าส่วน HMAC Tag
 * 2. 
 * @author orr
 */
class cipn {

    public $hmac = '';
    private $filename = '';
    private $string = '';
    private $CrLf = "\r\n";

    public function __construct($filename) {
        $this->filename = $filename;
    }

    private function load_txt() {
        $filename = $this->filename;
        $myfile = fopen($filename, "r") or die("Unable to open file!");
        $this->string = fread($myfile, filesize($filename));   
    }

    public function save_xml() {
        $this->load_txt();
        $segment = '<?xml version="1.0" encoding="windows-874"?>';
        $segment .= $this->CrLf;
        $segment .= $this->string;
        $segment .= $this->get_hmac($this->filename);
        $filename = "./XMLFiles/" . basename($this->filename , '.txt') . '.xml';
        $myfile = fopen($filename, "w") or die("Unable to open file!");
        fwrite($myfile, $segment);
        fclose($myfile);
    }

    private function get_hmac($filename) {
        $this->hmac = hash_file('md5', $filename);
        return "<?EndNote HMAC = \"$this->hmac\" ?>";
    }
}

$path_foder = "./TXTFiles/*.txt";
$list_files = glob($path_foder);
foreach ($list_files as $filename) {
    $my = new cipn($filename);
    $my->save_xml();
    printf("$filename size " . filesize($filename) . "  " . date('Ymd H:i:s') . " hmac " . $my->hmac . "\n");
}
