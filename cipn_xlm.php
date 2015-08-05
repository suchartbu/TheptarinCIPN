<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class my_exception extends Exception {
    
}

/**
 * โปรแกรมสร้างไฟล์ CIPN ตัวอย่างข้อมูลจากไฟล์ 11720-CIPN-5800001-20150517213147.xml
 * 1. สร้าง xlm ไฟล์เริ่มต้นด้วย DomDocument('1.0', 'utf-8')
 * 2. สร้างแต่ละส่วนของ xml ทั้งหมด
 * 3. แปลงไฟล์ utf-8 เป็น windows-874
 * @author orr
 */
class cipn_xlm {

    /**
     * XML Object ของ DomDocument 
     */
    private $dom = null;

    /**
     * AN. รหัสผู้ป่วยใน
     */
    private $an = 0;

    /**
     * ค่าตัวเลขวันที่เวลาที่ของไฟล์ รูปแบบ YYYYMMDDHHMMSS
     */
    private $file_datetime = 0;

    /**
     * ข้อมูลเกี่ยวกับผู้ป่วยที่เบิกค่ารักษา จากตาราง drgs_ipadt
     * 1. IPADT ค่าตามที่กำหนดใน XML
     * 2. auth_code รหัสอ้างอิงจากระบบ PAA
     * 3. auth_dt วันทีเวลาในเอกสารตอบกลับ PAA
     */
    private $drgs_ipadt = null;

    /**
     * ข้อมูลวินิจฉัยและหัตถการ จากตาราง drgs_ipdxop
     * 1. auth_code รหัสอ้างอิงจากระบบ PAA
     * 2. ipdxop ค่าตามที่กำหนดใน XML ส่วนแรก
     * 3. datein วันเวลาที่เริ่ม
     * 4. dateout วันเวลาที่สิ้นสุด
     */
    private $drgs_ipdxop = null;

    public function __construct($an) {
        if ($an == 0) {
            throw new Exception('AN. มีค่าเป็นศูนย์');
        }
        $this->an = $an; //เพิ่มส่วนตรวจสอบ AN.
        $this->get_drgs_ipadt();
        $this->dom = new DomDocument('1.0', 'utf-8');
        $this->dom->preserveWhiteSpace = FALSE;
        $this->dom->formatOutput = TRUE;
        $this->dom->load('utf8.xml');
        $this->file_datetime = date('YmdHis');
        $this->Header();
        $this->ClaimAuth();
        $this->IPADT();
        $this->IPDxOp();

        echo $this->dom->saveXML();
    }

    /**
     * ตรวจสอบข้อมูลจาก AN. เพื่อจัดทำไฟล์ CIPN 
     * ถ้าถูกต้องจะได้ค่าเพื่อใช้กับ ClaimAuth และ IPADT
     * @access private
     */
    private function get_drgs_ipadt() {
        $dsn = 'mysql:host=10.1.99.6;dbname=theptarin_utf8';
        $username = 'orr-projects';
        $password = 'orr-projects';
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );
        $db_conn = new PDO($dsn, $username, $password, $options);
        $sql = "SELECT concat(`an`,'|',`hn`,'|',`idtype`,'|',`pidpat`,'|',`title`,'|',`namepat`,'|',DATE_FORMAT( `dob`, '%Y%m%d' ),'|',`sex`,'|',`marriage`,'|',`changwat`,'|',`amphur`,'|',`nation`,'|',DATE_FORMAT( `dateadm`, '%Y%m%d' ),'|',`timeadm`,'|',DATE_FORMAT(`datedsc`, '%Y%m%d' ),'|',`timedsc`,'|',`leaveday`,'|',`dconfirm`,'|',`dischs`,'|',`discht`,'|',`adm_w`,'|',`dischward`,'|',`dept`,'|',`svctype`,'|',`svccode`,'|',`ubclass`,'|',`ucareplan`,'|',`projcode`,'|',`eventcode`,'|',`usercode`) AS `ipadt` , `auth_code`,`auth_dt` FROM `drgs_ipadt` WHERE `an` = :an";
        $stmt = $db_conn->prepare($sql);
        $stmt->execute(array("an" => $this->an));
        $this->drgs_ipadt = $stmt->fetch();

        //print_r($this->drgs_ipadt);
    }

    /**
     * ค้นหาข้อมูลจาก AuthCode ของ PAA ที่เบิกค่ารักษา เพื่อใช้ในส่วน IPDxOP
     * @access private
     */
    private function get_drgs_ipdxop($auth_code) {
        $dsn = 'mysql:host=10.1.99.6;dbname=theptarin_utf8';
        $username = 'orr-projects';
        $password = 'orr-projects';
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );
        $db_conn = new PDO($dsn, $username, $password, $options);
        $sql = "SELECT  `id`,`auth_code`,concat(`class`,'|',`subclass`,'|',`code`,'|',`dr`) AS `ipdxop` , `datein`, `dateout` FROM `drgs_ipdxop` WHERE `auth_code` = :auth_code";
        $stmt = $db_conn->prepare($sql);
        $stmt->execute(array("auth_code" => $auth_code));
        $rec_count = $stmt->rowCount();
        $this->drgs_ipdxop = $stmt->fetchAll();
        return $rec_count;
    }

    /**
     * กำหนดรูปแบบข้อมูลวันที่ตามที่กำหนด
     * ตัวอย่างรูปแบบ '20150517:141800' กำหนดเป็น 'Ymd:His'
     * กรณีเป็นวันที่ก่อน 01/01/2015 จะคืนค่าว่าง แทนค่าวันที่
     * @param string $str_date ตัวอักษรวันที่
     * @param string $str_format รูปแบบวันที่เวลา ที่กำหนด
     * @return string ตัวอักษรวันที่ตามรูปแบบที่กำหนด
     */
    private function get_data_format($str_date, $str_format = 'Ymd:His') {
        $obj_date = new DateTime($str_date);
        if ($obj_date->getTimestamp() > strtotime('2015-01-01')) {
            $my_date = date_format($obj_date, $str_format);
        } else {
            $my_date = null;
        }
        return $my_date;
    }

    /**
     * Header เป็นข้อมูลที่เกี่ยวข้องกับประเภทเอกสาร และผู้จัดทำเอกสาร ซึ่งรพ. จะใช้ AN. เป็นรหัสอ้างอิง
     * @access private
     */
    private function Header() {
        $this->dom->getElementsByTagName('DocCLass')->item(0)->nodeValue = 'IPDClaim';
        $this->dom->getElementsByTagName('DocSysID')->item(0)->nodeValue = 'CIPN';
        $this->dom->getElementsByTagName('serviceEvent')->item(0)->nodeValue = 'ADT';
        $this->dom->getElementsByTagName('authorID')->item(0)->nodeValue = '11720';
        $this->dom->getElementsByTagName('authorName')->item(0)->nodeValue = 'รพ.เทพธารินทร์';
        $this->dom->getElementsByTagName('DocumentRef')->item(0)->nodeValue = $this->an;
        $this->dom->getElementsByTagName('effectiveTime')->item(0)->nodeValue = $this->file_datetime;
    }

    /**
     * ClaimAuth เป็นข้อมูลเอกสารการขอนุมัติ PAA จากโปรแกรม drgs_ipadt.php
     * @access private
     */
    private function ClaimAuth() {
        $this->dom->getElementsByTagName('AuthCode')->item(0)->nodeValue = $this->drgs_ipadt['auth_code']; //รหัสอ้างอิงจากระบบ PAA 'PHG8SSJ00'
        $this->dom->getElementsByTagName('AuthDT')->item(0)->nodeValue = $this->get_data_format($this->drgs_ipadt['auth_dt'], 'Ymd:His'); //วันที่เวลาในเอกสารตอบกลับ PAA '20150517:141800'
    }

    /**
     * IPADT เป็นข้อมูลเกี่ยวกับผู้ป่วย การรับ การจำหน่าย ฯลฯ โปรแกรม drgs_ipadt.php
     * @access private
     */
    private function IPADT() {
        $this->dom->getElementsByTagName('IPADT')->item(0)->nodeValue = $this->drgs_ipadt['ipadt'];
    }

    /**
     * IPDxOp ส่วน xml ข้อมูลวินิจฉัยและหัตถการ
     * @access private
     */
    private function IPDxOp() {
        $rec_count = $this->get_drgs_ipdxop($this->drgs_ipadt['auth_code']);
        $node_value = "";
        foreach ($this->drgs_ipdxop as $value) {
            $node_value .= $this->an . "|" . $value['ipdxop'] . "|" . $this->get_data_format($value['datein']) . "|" . $this->get_data_format($value['dateout']) . "|\n";
        }
        $this->dom->getElementsByTagName('IPDxOp')->item(0)->setAttribute('Reccount', $rec_count);
        $this->dom->getElementsByTagName('IPDxOp')->item(0)->nodeValue = $node_value;
    }

    /**
     * save เพื่อสร้างไฟล์ CIPN กำหนดชื่อไฟล์ตามรูป [รหัสรพ.]-CIPN-[AN.]-[$this->file_datetime]-utf8.xml
     * @param string an รหัสอ้างอิงเป็น AN. รพ.
     * @access private
     */
    public function save() {
        $this->dom->save('11720-CIPN-' . $this->an . '-' . $this->file_datetime . '-utf8.xml');
    }

}

$my = new cipn_xlm('5800001');
$my->save();
