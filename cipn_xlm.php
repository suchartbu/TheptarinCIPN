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
 * @link  ข้อกำหนดไฟล์ CIPN
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
     * ชื่อไฟล์ CIPN 
     */
    private $file_name = "";

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

    /**
     * ข้อมูลค่ารักษา จากตาราง drgs_invoices
     * 1. invoices ค่าตามที่กำหนดใน XML
     * 2. amount ยอดรวมแต่ละรายการที่หักส่วนลดแล้ว
     */
    private $drgs_invoices = null;

    /**
     * ข้อมูลค่ารักษา จากตาราง drgs_cipn_claim
     * 
     */
    private $drgs_cipn_claim = null;

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
        $this->Invoices();
        $this->CIPNClaim();

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
     * ค้นหาข้อมูลจาก AN. ของ PAA ที่เบิกค่ารักษา เพื่อใช้ในส่วน Invoices
     * @access private
     */
    private function get_drgs_invoices() {
        $dsn = 'mysql:host=10.1.99.6;dbname=theptarin_utf8';
        $username = 'orr-projects';
        $password = 'orr-projects';
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );
        $db_conn = new PDO($dsn, $username, $password, $options);
        $sql = "SELECT CONCAT( `drgs_invoices`.`an`, '|', DATE_FORMAT( `servdate`, '%Y%m%d' ), '|', `drgs_charges`.`billgroup`, '|', `drgs_charges`.`cscode`, '|', `drgs_charges`.`code`, '|', `drgs_charges`.`unit`, '|', `drgs_invoices`.`qty`, '|', REPLACE(FORMAT(`drgs_invoices`.`rate`, 2), ',', ''), '|', `drgs_charges`.`revrate`, '|', CONCAT( `drgs_invoices`.`qty` * `drgs_invoices`.`rate`, '.00' ), '|', '0.00' ) AS `invoices`, `drgs_invoices`.`qty` * `drgs_invoices`.`rate` AS `amount`, `drgs_charges`.`billgroup` FROM `theptarin_utf8`.`drgs_charges` AS `drgs_charges`, `theptarin_utf8`.`drgs_invoices` AS `drgs_invoices` WHERE `drgs_invoices`.`an` = :an AND `drgs_invoices`.`code` = CONCAT( `drgs_charges`.`code`, '@', `drgs_charges`.`billgroup` ) ORDER BY `drgs_charges`.`billgroup` ASC";
        $stmt = $db_conn->prepare($sql);
        $stmt->execute(array("an" => $this->an));
        $rec_count = $stmt->rowCount();
        $this->drgs_invoices = $stmt->fetchAll();
        return $rec_count;
    }

    /**
     * ค้นหาข้อมูลจาก AN. ของ PAA ที่เบิกค่ารักษา เพื่อใช้ในส่วน CIPNClaim
     * @access private
     */
    private function get_drgs_cipn_claim() {
        $dsn = 'mysql:host=10.1.99.6;dbname=theptarin_utf8';
        $username = 'orr-projects';
        $password = 'orr-projects';
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );
        $db_conn = new PDO($dsn, $username, $password, $options);
        $sql = "SELECT CONCAT( `drgs_cipn_claim`.`an`, '|',`billgroup`, '|', `cscode`, '|' ,`csqty`, '|', REPLACE(FORMAT(`csrate`, 2), ',', ''), '|',`csrevrate`, '|', REPLACE(FORMAT(`claim`, 2), ',', ''), '|',REPLACE(FORMAT(`amount`, 2), ',', ''),'|',REPLACE(FORMAT(`discount`, 2), ',', ''),'|',`rcat`,'|',`srid`) AS `cipnclaim` , `claim` FROM `theptarin_utf8`.`drgs_charges` AS `drgs_charges`, `theptarin_utf8`.`drgs_cipn_claim` AS `drgs_cipn_claim` WHERE `drgs_cipn_claim`.`an` = :an AND `drgs_cipn_claim`.`code` = CONCAT( `drgs_charges`.`code`, '@', `drgs_charges`.`billgroup` ) ORDER BY `drgs_charges`.`billgroup` ASC";
        $stmt = $db_conn->prepare($sql);
        $stmt->execute(array("an" => $this->an));
        $rec_count = $stmt->rowCount();
        $this->drgs_cipn_claim = $stmt->fetchAll();
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
     * Invoices ส่วน xml ค่ารักษาทุกรายการ
     * @access private
     */
    private function Invoices() {
        $rec_count = $this->get_drgs_invoices();
        $node_value = "";
        $inv_total = 0;
        $inv_discount = 0;
        foreach ($this->drgs_invoices as $value) {
            $node_value .= $value['invoices'] . "|\n";
            $inv_total += $value['amount'];
        }
        $this->dom->getElementsByTagName('InvNumber')->item(0)->nodeValue = "58-0001"; //เลข Invoice ขนาดไม่เกิน 9 ตัวอักษร
        $this->dom->getElementsByTagName('InvDT')->item(0)->nodeValue = "20150517"; //วันเวลาที่ออก Invoice รูปแบบ YYYYMMDD
        $this->dom->getElementsByTagName('InvItems')->item(0)->setAttribute('Reccount', $rec_count);
        $this->dom->getElementsByTagName('InvItems')->item(0)->nodeValue = $node_value;
        $this->dom->getElementsByTagName('InvTotal')->item(0)->nodeValue = number_format($inv_total, 2, '.', ''); // รูปแบบ 0000.00
        $this->dom->getElementsByTagName('InvAddDiscount')->item(0)->nodeValue = number_format($inv_discount, 2, '.', '');
    }

    /**
     * CIPNClaim ส่วน xml ข้อมูลรายการเบิกค่ารักษาส่วนนอก DRG
     * ค่าห้อง ค่าอาหาร และค่าอวัยวะเทียมและอุปกรณ์
     * @access private
     */
    private function CIPNClaim() {
        $rec_count = $this->get_drgs_cipn_claim();
        $room_deduct = 0;
        $room_non_deduct = 0;
        $med_deduct = 0;
        $med_non_deduct = 0;
        $node_value = "";
        foreach ($this->drgs_cipn_claim as $value) {
            $node_value .= $value['cipnclaim'] . "|\n";
            $room_deduct += $value['claim'];
        }

        $this->dom->getElementsByTagName('FeeScheduleItems')->item(0)->setAttribute('Reccount', $rec_count);
        $this->dom->getElementsByTagName('FeeScheduleItems')->item(0)->nodeValue = $node_value; //รายการส่วนนอก DRG
        $this->dom->getElementsByTagName('DeductRoomBoard')->item(0)->nodeValue = number_format($room_deduct, 2, '.', ''); //รวมค่าห้องค่าอาหารส่วนที่เบิกได้
        $this->dom->getElementsByTagName('nonDeductRoomBoard')->item(0)->nodeValue = number_format($room_non_deduct, 2, '.', ''); //รวมค่าห้องค่าอาหารส่วนที่เกิน
        $this->dom->getElementsByTagName('DeductMedDev')->item(0)->nodeValue = number_format($med_deduct, 2, '.', ''); //รวมค่าอวัยวะเทียมฯ ที่เบิกได้
        $this->dom->getElementsByTagName('nonDeductMedDev')->item(0)->nodeValue = number_format($med_non_deduct, 2, '.', ''); //รวมค่าอวัยวะเทียมฯ ส่วนที่เกิน
    }

    /**
     * แปลงไฟล์ UTF8 เป็น TIS-620 ปรับรูปแบบไฟล์เพื่อให้ windows ใช้งานได้
     * @access private
     * @return string hash_value ของไฟล์
     */
    private function convert_xml() {
        $file_read = fopen($this->file_name . '-utf8.xml', "r") or die("Unable to open file!");
        $file_write = fopen($this->file_name . '.txt', "w") or die("Unable to open file!");
        fgets($file_read); //อ่านบรรทัดแรกก่อน
        while (!feof($file_read)) {
            $str_line = trim(fgets($file_read), "\n");
            if ($str_line != "") {
                fwrite($file_write, iconv("UTF-8", "tis-620", $str_line . "\r\n"));
            }
        }
        fclose($file_read);
        fclose($file_write);
        return hash_file("md5", $this->file_name . ".txt");
    }

    /**
     * สร้างไฟล์ XML CIPN 
     * @access private
     */
    private function create_xml($str_hash) {
        $file_read = fopen($this->file_name . '.txt', "r") or die("Unable to open file!");
        $file_write = fopen($this->file_name . '.xml', "w") or die("Unable to open file!");
        fwrite($file_write, '<?xml version="1.0" encoding="windows-874"?>');
        while (!feof($file_read)) {
            fwrite($file_write, fgets($file_read));
        }
        fwrite($file_write, '<?EndNote HMAC = "' . $str_hash . '" ?>');
    }

    /**
     * สร้างไฟล์ CIPN กำหนดชื่อไฟล์ตามรูป [รหัสรพ.]-CIPN-[AN.]-[$this->file_datetime].xml
     * @access private
     */
    public function save() {
        $this->file_name = '11720-CIPN-' . $this->an . '-' . $this->file_datetime;
        $this->dom->save($this->file_name . '-utf8.xml');
        $this->create_xml($this->convert_xml());
    }

}

$my = new cipn_xlm('5800001');
$my->save();
