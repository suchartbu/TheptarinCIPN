<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of xml
 *
 * @author orr
 */
class cipn_xml {

    private $CrLf = "\r\n";

    //put your code here
    public function __construct() {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="windows-874"?><CIPN></CIPN>');
        $header = $xml->addChild('Header');
        $docclass = $header->addChild('DocCLass', 'IPDClaim');
        $docclass->addAttribute('version', '1.0');
        $header->addChild('DocSysID', 'CIPN');
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        echo $dom->saveXML();
    }
}

$my = new cipn_xml();
