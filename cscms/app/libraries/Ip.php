<?php
/**
 * @Cscms open source management system
 * @copyright 2009-2015 chshcms.com. All rights reserved.
 * @Author:Cheng Jie
 * @Dtime:2014-07-26
 */
if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * IP地理位置类
 */
class Ip {
	
	private $file;
	private $name;
	public $address;
	
	/**
     * 构造函数
     */
    public function __construct() {

		$fullipfile = CSCMSPATH.'ipdata/qqwry.dat';
		$miniipfile = CSCMSPATH.'ipdata/mini.dat';
		if (@file_exists($fullipfile)) {
			$this->name = 'full';
			$this->file = $fullipfile;
		} else {
			$this->name = 'mini';
			$this->file = $miniipfile;
		}
    }
	
	/**
	 * 设置ip
	 */
	public function set($ip) {

		$this->address = '';

		if (preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $ip)) {
			$iparray = explode('.', $ip);
			if ($iparray[0] == 10 || $iparray[0] == 127 || ($iparray[0] == 192 && $iparray[1] == 168) || ($iparray[0] == 172 && ($iparray[1] >= 16 && $iparray[1] <= 31))) {
				$this->address = '';
			} elseif ($iparray[0] > 255 || $iparray[1] > 255 || $iparray[2] > 255 || $iparray[3] > 255) {
				$this->address = '';
			} else {
				$this->address = $this->name == 'mini' ? $this->_mini($ip) : $this->_full($ip);
			}
		}

		return $this;
	}
	
	/**
	 * 直接获取ip实际地址
	 */
	public function address($ip) {
		if($ip=='127.0.0.1'){
		      return '本机IP';
		}
		$this->set($ip);
		return get_bm($this->address);
	}

	private function _mini($ip) {

		static $fp = NULL, $offset = array(), $index = NULL;

		$ipdot = explode('.', $ip);
		$ip = pack('N', ip2long($ip));

		$ipdot[0] = (int)$ipdot[0];
		$ipdot[1] = (int)$ipdot[1];

		if($fp === NULL && $fp = @fopen($this->file, 'rb')) {
			$offset = @unpack('Nlen', @fread($fp, 4));
			$index = @fread($fp, $offset['len'] - 4);
		} elseif($fp == FALSE) {
			return '';
		}

		$length = $offset['len'] - 1028;
		$start = @unpack('Vlen', $index[$ipdot[0] * 4] . $index[$ipdot[0] * 4 + 1] . $index[$ipdot[0] * 4 + 2] . $index[$ipdot[0] * 4 + 3]);

		for ($start = $start['len'] * 8 + 1024; $start < $length; $start += 8) {

			if ($index{$start} . $index{$start + 1} . $index{$start + 2} . $index{$start + 3} >= $ip) {
				$index_offset = @unpack('Vlen', $index{$start + 4} . $index{$start + 5} . $index{$start + 6} . "\x0");
				$index_length = @unpack('Clen', $index{$start + 7});
				break;
			}
		}

		@fseek($fp, $offset['len'] + $index_offset['len'] - 1024);
		if($index_length['len']) {
			return @fread($fp, $index_length['len']);
		} else {
			return '';
		}
	}

	private function _full($ip) {

		if(!$fd = @fopen($this->file, 'rb')) {
			return '';
		}

		$ip = explode('.', $ip);
		$ipNum = $ip[0] * 16777216 + $ip[1] * 65536 + $ip[2] * 256 + $ip[3];

		if(!($DataBegin = fread($fd, 4)) || !($DataEnd = fread($fd, 4)) ) return;
		@$ipbegin = implode('', unpack('L', $DataBegin));
		if($ipbegin < 0) $ipbegin += pow(2, 32);
		@$ipend = implode('', unpack('L', $DataEnd));
		if($ipend < 0) $ipend += pow(2, 32);
		$ipAllNum = ($ipend - $ipbegin) / 7 + 1;

		$BeginNum = $ip2num = $ip1num = 0;
		$ipAddr1 = $ipAddr2 = '';
		$EndNum = $ipAllNum;

		while($ip1num > $ipNum || $ip2num < $ipNum) {
			$Middle= intval(($EndNum + $BeginNum) / 2);

			fseek($fd, $ipbegin + 7 * $Middle);
			$ipData1 = fread($fd, 4);
			if(strlen($ipData1) < 4) {
				fclose($fd);
				return '';
			}
			$ip1num = implode('', unpack('L', $ipData1));
			if($ip1num < 0) $ip1num += pow(2, 32);

			if($ip1num > $ipNum) {
				$EndNum = $Middle;
				continue;
			}

			$DataSeek = fread($fd, 3);
			if(strlen($DataSeek) < 3) {
				fclose($fd);
				return '';
			}
			$DataSeek = implode('', unpack('L', $DataSeek.chr(0)));
			fseek($fd, $DataSeek);
			$ipData2 = fread($fd, 4);
			if(strlen($ipData2) < 4) {
				fclose($fd);
				return '';
			}
			$ip2num = implode('', unpack('L', $ipData2));
			if($ip2num < 0) $ip2num += pow(2, 32);

			if($ip2num < $ipNum) {
				if($Middle == $BeginNum) {
					fclose($fd);
					return '';
				}
				$BeginNum = $Middle;
			}
		}

		$ipFlag = fread($fd, 1);
		if($ipFlag == chr(1)) {
			$ipSeek = fread($fd, 3);
			if(strlen($ipSeek) < 3) {
				fclose($fd);
				return '';
			}
			$ipSeek = implode('', unpack('L', $ipSeek.chr(0)));
			fseek($fd, $ipSeek);
			$ipFlag = fread($fd, 1);
		}

		if($ipFlag == chr(2)) {
			$AddrSeek = fread($fd, 3);
			if(strlen($AddrSeek) < 3) {
				fclose($fd);
				return '';
			}
			$ipFlag = fread($fd, 1);
			if($ipFlag == chr(2)) {
				$AddrSeek2 = fread($fd, 3);
				if(strlen($AddrSeek2) < 3) {
					fclose($fd);
					return '';
				}
				$AddrSeek2 = implode('', unpack('L', $AddrSeek2.chr(0)));
				fseek($fd, $AddrSeek2);
			} else {
				fseek($fd, -1, SEEK_CUR);
			}

			while(($char = fread($fd, 1)) != chr(0))
			$ipAddr2 .= $char;

			$AddrSeek = implode('', unpack('L', $AddrSeek.chr(0)));
			fseek($fd, $AddrSeek);

			while(($char = fread($fd, 1)) != chr(0))
			$ipAddr1 .= $char;
		} else {
			fseek($fd, -1, SEEK_CUR);
			while(($char = fread($fd, 1)) != chr(0))
			$ipAddr1 .= $char;

			$ipFlag = fread($fd, 1);
			if($ipFlag == chr(2)) {
				$AddrSeek2 = fread($fd, 3);
				if(strlen($AddrSeek2) < 3) {
					fclose($fd);
					return '';
				}
				$AddrSeek2 = implode('', unpack('L', $AddrSeek2.chr(0)));
				fseek($fd, $AddrSeek2);
			} else {
				fseek($fd, -1, SEEK_CUR);
			}
			while(($char = fread($fd, 1)) != chr(0))
			$ipAddr2 .= $char;
		}
		fclose($fd);

		if(preg_match('/http/i', $ipAddr2)) {
			$ipAddr2 = '';
		}
		$ipaddr = "$ipAddr1 $ipAddr2";
		$ipaddr = preg_replace('/CZ88\.NET/is', '', $ipaddr);
		$ipaddr = preg_replace('/^\s*/is', '', $ipaddr);
		$ipaddr = preg_replace('/\s*$/is', '', $ipaddr);
		if(preg_match('/http/i', $ipaddr) || $ipaddr == '') {
			$ipaddr = '';
		}

		return '- '.$ipaddr;

	}
	
	
}
