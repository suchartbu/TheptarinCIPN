<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * โปรแกรมสร้างไฟล์ CIPN ตัวอย่างข้อมูลจากไฟล์ 11720-CIPN-5800001-20150517213147.xml
 * 1. สร้าง xlm ไฟล์เริ่มต้นด้วย DomDocument('1.0', 'utf-8')
 * 2. สร้างแต่ละส่วนของ xml ทั้งหมด
 * 3. แปลงไฟล์ utf-8 เป็น windows-874
 * @author orr
 */
class cipn_xlm {
    /*
     *
     */
    private $dom = null;

    //put your code here
    public function __construct($an) {
        $filename = 'utf8.xml';
        $this->dom = new DomDocument('1.0', 'utf-8');
        $this->dom->preserveWhiteSpace = FALSE;
        $this->dom->formatOutput = TRUE;
        $this->dom->load($filename);
        $this->Header($an);
        $this->ClaimAuth();
        $this->IPADT();
        $this->IPDxOp();

        echo $this->dom->saveXML();
    }
    
    /**
     * Header เป็นข้อมูลที่เกี่ยวข้องกับประเภทเอกสาร และผู้จัดทำเอกสาร ซึ่งรพ. จะใช้ AN. เป็นรหัสอ้างอิง
     * @param string an รหัสอ้างอิงเป็น AN. รพ.
     * @access private
     */
    private function Header($an) {
        $this->dom->getElementsByTagName('DocCLass')->item(0)->nodeValue = 'IPDClaim';
        $this->dom->getElementsByTagName('DocSysID')->item(0)->nodeValue = 'CIPN';
        $this->dom->getElementsByTagName('serviceEvent')->item(0)->nodeValue = 'ADT';
        $this->dom->getElementsByTagName('authorID')->item(0)->nodeValue = '11720';
        $this->dom->getElementsByTagName('authorName')->item(0)->nodeValue = 'รพ.เทพธารินทร์';
        $this->dom->getElementsByTagName('DocumentRef')->item(0)->nodeValue = $an; // AN.ของรพ.
        $this->dom->getElementsByTagName('effectiveTime')->item(0)->nodeValue = '20150517093147'; //วันเวลาของไฟล์ รอแก้ไข
    }
    
    /**
     * ClaimAuth เป็นข้อมูลเอกสารการขอนุมัติ PAA จากโปรแกรม drgs_ipadt.php
     * @access private
     */
    private function ClaimAuth() {
        $this->dom->getElementsByTagName('AuthCode')->item(0)->nodeValue = 'PHG8SSJ00'; //รหัสอ้างอิงจากระบบ PAA
        $this->dom->getElementsByTagName('AuthDT')->item(0)->nodeValue = '20150517:141800'; //วันที่เวลาในเอกสารตอบกลับ PAA
    }

    private function IPADT() {
        $this->dom->getElementsByTagName('IPADT')->item(0)->nodeValue = '5800001|58-00001|0|1172000000001|น.ส.|ทดสอบ 1 เทพธารินทร์|19570913|2|2|10|01|99|20150512|153500|20150517|120000|0|N|1|1||IPD|03|IP||||||'; //รหัสอ้างอิง PAA
    }

    private function IPDxOp() {
        $node_value = "5800001|P|1|741|23478|20150512:192000|20150512:200500\n";
        $node_value .= "5800001|D|1|O342|23478||\n";
        $node_value .= "5800001|D|2|O820|23478||\n";
        $this->dom->getElementsByTagName('IPDxOp')->item(0)->setAttribute('Reccount', '3');
        $this->dom->getElementsByTagName('IPDxOp')->item(0)->nodeValue = $node_value;
        
    }

    public function save() {
        $this->dom->save('utf8-out.xml');
    }

}

$my = new cipn_xlm('5800001');
$my->save();
