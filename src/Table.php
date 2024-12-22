<?php
/********************************************
 * DBF-file Structure Reader
 *
 * Author: Chizhov Nikolay <admin@kgd.in>
 * (c) 2019-2024 CIOB "Inok"
 ********************************************/

namespace Inok\Dbf;

use Exception;

class Table {
  private $headers = null;
  private $columns = null;
  public $error = false;
  public $error_info = null;

  private $db, $fp;

  private $versions = [
    2   => ["FoxBase"],
    3   => ["dBASE III", "dBASE IV", "dBASE 5", "FoxPro", "FoxBASE+"],
    4   => ["dBASE 7"],
    48  => ["Visual FoxPro"],
    49  => ["Visual FoxPro"],
    50  => ["Visual FoxPro"],
    67  => ["dBASE IV", "dBASE 5"],
    99  => ["dBASE IV", "dBASE 5"],
    131 => ["dBASE III", "FoxBASE+", "FoxPro"],
    139 => ["dBASE IV", "dBASE 5"],
    140 => ["dBASE 7"],
    203 => ["dBASE IV", "dBASE 5"],
    229 => ["SMT"],
    235 => ["dBASE IV", "dBASE 5"],
    245 => ["FoxPro"],
    251 => ["FoxBASE"]
  ];
  private $memo = [
    "versions" => [131, 139, 140, 203, 229, 235, 245, 251],
    "formats" => [
      "dbt" => [131, 139, 140, 203, 235, 251],
      "fpt" => [245, 48, 49, 50],
      "smt" => [229]
    ]
  ];

  private $charsets = [
    0   => 866, //If charset not defined
    1   => 437,    2   => 850,    3   => 1252,    4   => 10000,    8   => 865,
    9   => 437,    10  => 850,    11  => 437,     13  => 437,      14  => 850,
    15  => 437,    16  => 850,    17  => 437,     18  => 850,      19  => 932,
    20  => 850,    21  => 850,    22  => 437,     23  => 850,      24  => 437,
    25  => 437,    26  => 850,    27  => 437,     28  => 863,      29  => 850,
    31  => 852,    34  => 852,    35  => 852,     36  => 860,      37  => 850,
    38  => 866,    55  => 850,    64  => 852,     77  => 936,      78  => 949,
    79  => 950,    80  => 874,    88  => 1252,    89  => 1252,     100 => 852,
    101 => 866,    102 => 865,    103 => 861,     104 => 895,      105 => 866,
    106 => 737,    107 => 857,    108 => 863,     120 => 950,      121 => 949,
    122 => 936,    123 => 932,    124 => 874,     134 => 737,      135 => 852,
    136 => 857,    150 => 10007,  151 => 10029,   152 => 10006,    200 => 1250,
    201 => 1251,   202 => 1254,   203 => 1253,    204 => 1257
  ];
  private $dbase7 = false, $v_foxpro = false;

  /**
   * @throws Exception
   */
  public function __construct($dbPath, $charset = null){
    $this->db = $dbPath;
    if (!is_null($charset)) {
      if (!is_numeric($charset)) {
        throw new Exception("Set not correct charset. Allows only digits.");
      }
      $this->charsets[0] = $charset;
    }
    $this->open();
  }

  public function __destruct() {
    $this->close();
  }

  /**
   * @throws Exception
   */
  private function open() {
    if (!file_exists($this->db)) {
      throw new Exception(sprintf('File %s cannot be found', $this->db));
    }
    $this->fp = fopen($this->db, "rb");
  }

  public function getHeaders() {
    if (!$this->error && is_null($this->headers)) {
      $this->readHeaders();
    }
    return $this->headers;
  }

  public function getColumns() {
    $this->getHeaders();
    if (!$this->error && is_null($this->columns)) {
      $this->readTableHeaders();
    }
    return $this->columns;
  }

  public function getData() {
    $this->getColumns();
    return $this->fp;
  }

  public function close() {
    if (get_resource_type($this->fp) === "file") {
      fclose($this->fp);
    }
  }

  private function readHeaders() {
    $data = fread($this->fp, 32);
    $file = pathinfo($this->db);
    $this->headers = [
      "dbf_file" => $this->db,
      "table" => strtolower(basename($file["filename"], ".dbf")),
      "version" => unpack("C", $data[0])[1],
      "date" => $this->getDate(unpack("C*", substr($data, 1, 3))),
      "records" => unpack("L", substr($data, 4, 4))[1],
      "header_length" => unpack("S", substr($data, 8, 2))[1],
      "record_length" => unpack("S", substr($data, 10, 2))[1],
      "unfinish_transaction" => unpack("C", $data[14])[1],
      "coded" => unpack("C", $data[15])[1],
      "mdx_flag" => unpack("C", $data[28])[1],
      "charset" => unpack("C", $data[29])[1],
      "checks" => [
        unpack("S", substr($data, 12, 2))[1], unpack("S", substr($data, 30, 2))[1]
      ]
    ];

    if ($this->headers["checks"][0] != 0) {
      $this->error = true;
      $this->error_info = "Not correct DBF file by headers";
      return;
    }
    $this->headers["charset_name"] = "cp" . $this->charsets[$this->headers["charset"]];

    if (in_array("dBASE 7", $this->versions[$this->headers["version"]])) {
      $this->dbase7 = true;
      $this->headers["columns"] = ($this->headers["header_length"] - 68) / 48;
    } elseif (in_array("Visual FoxPro", $this->versions[$this->headers["version"]])) {
      $this->v_foxpro = true;
      $this->headers["memo"] = (in_array($this->headers["mdx_flag"], [2, 3, 6, 7]));
      $this->headers["columns"] = ($this->headers["header_length"] - 296) / 32;
    } else {
      $this->headers["columns"] = ($this->headers["header_length"] - 33) / 32;
    }

    if (!isset($this->headers["memo"])) {
      $this->headers["memo"] = in_array($this->headers["version"], $this->memo["versions"]);
    }
    if ($this->headers["memo"]) {
      $this->headers["memo_file"] = ($mfile = $this->getMemoFile($file["dirname"] . "/" . $file["filename"])) ? $mfile : null;
    }

    $this->headers["version_name"] =
      implode(", ", $this->versions[$this->headers["version"]]) . " " . ($this->headers["memo"] ? "with" : "without") . " memo-fields";
    unset($this->headers["checks"], $this->headers["header_length"]);
  }

  private function readTableHeaders() {
    if (!$this->error && is_null($this->headers)) {
      $this->readHeaders();
    }
    if (!$this->error) {
      for ($i = 0; $i < $this->headers["columns"]; $i++) {
        $data = fread($this->fp, ($this->dbase7) ? 48 : 32);
        if ($this->dbase7) {
          $this->columns[$i] = [
            "name" => strtolower(trim(substr($data, 0, 32))),
            "type" => $data[32],
            "length" => unpack("C", $data[33])[1],
            "decimal" => unpack("C", $data[34])[1],
            "mdx_flag" => unpack("C", $data[37])[1],
            "auto_increment" => unpack("L", substr($data, 40, 4))[1]
          ];
        }
        else {
          $this->columns[$i] = [
            "name" => strtolower(trim(substr($data, 0, 11))),
            "type" => $data[11],
            "length" => unpack("C", $data[16])[1],
            "decimal" => unpack("C", $data[17])[1],
            "mdx_flag" => unpack("C", $data[31])[1],
          ];
          if ($this->v_foxpro) {
            $this->columns[$i]["flag"] = unpack("C", $data[18])[1];
            $this->columns[$i]["system"] = ($this->columns[$i]["flag"] == 1);
            $this->columns[$i]["has_null"] = in_array($this->columns[$i]["flag"], [2, 6]);
            $this->columns[$i]["binary"] = in_array($this->columns[$i]["flag"], [4, 6]);
            $this->columns[$i]["auto_increment"] = ($this->columns[$i]["flag"] == 12);
            if ($this->columns[$i]["auto_increment"]) {
              $this->columns[$i]["auto_increment_next"] = unpack("L", substr($data, 19, 4))[1];
              $this->columns[$i]["auto_increment_step"] = unpack("C", $data[23])[1];
            }
          }
          else {
            $this->columns[$i]["mdx_flag"] = unpack("C", $data[31])[1];
          }
        }
        if ($this->columns[$i]["type"] == "C") {
          $this->columns[$i]["length"] = unpack("S", substr($data, ($this->dbase7) ? 33 : 16, 2))[1];
          $this->columns[$i]["decimal"] = 0;
        }
      }
    }
    $terminal_byte = unpack("C", fread($this->fp, 1))[1];
    if ($terminal_byte != 13) {
      $this->error = true;
      $this->error_info = "Not correct DBF file by columns";
    }
    if ($this->v_foxpro) {
      fread($this->fp, 263);
    }
  }

  private function getDate($data) {
    return $data[3].".".$data[2].".".($data[1] > 70 ? 1900 + $data[1] : 2000 + $data[1]);
  }

  private function getMemoFile($file) {
    foreach ($this->memo["formats"] as $format => $versions) {
      if (in_array($this->headers["version"], $versions)) {
        return $this->fileExists($file.".".$format);
      }
    }
    return false;
  }

  private function fileExists($fileName) {
    if (file_exists($fileName)) {
      return $fileName;
    }

    // Handle case-insensitive requests
    $directoryName = dirname($fileName);
    $fileArray = glob($directoryName . '/*', GLOB_NOSORT);
    $fileNameLowerCase = strtolower($fileName);
    foreach($fileArray as $file) {
      if(strtolower($file) == $fileNameLowerCase) {
        return $file;
      }
    }
    return false;
  }
}
