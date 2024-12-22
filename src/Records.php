<?php
/********************************************
 * DBF-file records Reader
 *
 * Author: Chizhov Nikolay <admin@kgd.in>
 * (c) 2019-2024 CIOB "Inok"
 ********************************************/

namespace Inok\Dbf;

use Exception;

class Records {
  private $fp, $headers, $columns, $memo, $encode;
  private $records = 0;

  private $v_fox_versions = [48, 49, 50];
  private $v_fox = false;
  private $nullFlagColumns = [];
  private $logicals = ['t', 'y', 'д'];
  private $notTrimTypes = ["M", "P", "G", "I", "Y", "T", "0"];

  /**
   * @throws Exception
   */
  public function __construct($data, $encode = "utf-8", $headers = null, $columns = null) {
    if ($data instanceof Table) {
      $this->headers = $data->getHeaders();
      $this->columns = $data->getColumns();
      $this->fp = $data->getData();
    }
    else {
      if (is_null($headers) || is_null($columns)) {
        throw new Exception('Not correct data in Record class');
      }
      $this->fp = $data;
      $this->headers = $headers;
      $this->columns = $columns;
    }
    $this->encode = $encode;
    if ($this->headers["memo"] && !is_null($this->headers["memo_file"])) {
      $this->memo = new Memo($this->headers["memo_file"]);
    }
    $this->v_fox = in_array($this->headers["version"], $this->v_fox_versions);
  }

  public function nextRecord() {
    if ($this->records >= $this->headers["records"]) {
      if ($this->memo instanceof Memo) {
        unset($this->memo);
      }
      return false;
    }
    $record = [];
    $data = fread($this->fp, $this->headers["record_length"]);
    $record["deleted"] = (unpack("C", $data[0])[1] == 42);
    $pos = 1;
    foreach ($this->columns as $column) {
      $sub_data = (in_array($column["type"], $this->notTrimTypes)) ? substr($data, $pos, $column["length"]) : trim(substr($data, $pos, $column["length"]));
      switch($column["type"]) {
        case "F":
        case "N":
          $record[$column["name"]] = (is_numeric($sub_data)) ? (($column["decimal"]) ? (float) $sub_data : (int) $sub_data) : null;
          break;
        case "Y":
          $decimal = intval(str_pad("1", $column["decimal"] + 1, "0"));
          $record[$column["name"]] = round(unpack("Q", $sub_data)[1] / $decimal, $column["decimal"]);
          break;
        case "I":
          $record[$column["name"]] = unpack("l", $sub_data)[1];
          break;
        case "@":
        case "T":
          $record[$column["name"]] = $this->getDateTime($sub_data);
          break;
        case "D":
          $record[$column["name"]] = empty($sub_data) ? null : $sub_data;
          break;
        case "L":
          $record[$column["name"]] =  ($sub_data == "?" || empty($sub_data)) ? null : (in_array(strtolower($sub_data), $this->logicals));
          break;
        case "C":
          $record[$column["name"]] = $this->convertChar($sub_data);
          break;
        case "M":
        case "P":
        case "G":
          $sub_data = (strlen($sub_data) == 4) ? unpack("L", $sub_data)[1] : (int)$sub_data;
          if (!$sub_data) {
            $record[$column["name"]] = "";
          } else {
            $record[$column["name"]] = $this->getMemo($sub_data, ($column["type"] == "M"));
          }
          break;
        case "0":
          $value = intval(unpack("C*", $sub_data)[1]);
          $record[$column["name"]] = $value;
          foreach ($this->nullFlagColumns as $index => $name) {
            if (($value >> $index) & 1) {
              $record[$name] = null;
            }
          }
          break;
      }
      $this->checkNullColumn($column);
      $pos += $column["length"];
    }
    $this->records++;
    return $record;
  }

  private function checkNullColumn($column) {
    if (!empty($column["has_null"])) {
      $this->nullFlagColumns[] = $column["name"];
    }
  }

  private function getMemo($data, $convert = true) {
    $memo = $this->memo->getMemo($data);
    return ($convert) ? $this->convertChar($memo["text"]) : $memo["text"];
  }

  private function convertChar($data) {
    return iconv($this->headers["charset_name"], $this->encode, str_replace("\r\n", "\n", $data));
  }

  private function getDateTime($data) {
    $data = trim($data);
    if (empty($data)) {
      return null;
    }
    if (strlen($data) == 14) {
      return $data;
    }
    $dateData = unpack("L", substr($data, 0, 4))[1];
    $timeData = unpack("L", substr($data, 4, 4))[1];
    return gmdate("YmdHis", jdtounix($dateData) + intval($timeData / 1000));
  }
}
