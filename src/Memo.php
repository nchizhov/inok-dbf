<?php
/********************************************
 * DBF-file MEMO-fields Reader
 *
 * Author: Chizhov Nikolay <admin@kgd.in>
 * (c) 2019-2024 CIOB "Inok"
 ********************************************/

namespace Inok\Dbf;

use Exception;

class Memo {
  private $headers = null;

  private $db, $fp;
  private $signature = [
    0 => "template|picture",
    1 => "text",
    2 => "object",
    4294903808 => "dbaseIV"
  ];
  private $isBase4 = false;
  private $isBase3 = false;

  /**
   * @throws Exception
   */
  public function __construct($file) {
    $this->db = $file;

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
      throw new Exception(sprintf('Memo-file %s cannot be found', $this->db));
    }
    $this->fp = fopen($this->db, "rb");
  }

  public function getHeaders() {
    if (is_null($this->headers)) {
      $this->readHeaders();
    }
    return $this->headers;
  }

  public function getMemo($block) {
    if (is_null($this->headers)) {
      $this->readHeaders();
    }
    return $this->readMemo($block);
  }

  public function close() {
    if (get_resource_type($this->fp) === "file") {
      fclose($this->fp);
    }
  }

  private function readHeaders() {
    $data = fread($this->fp, 512);
    $fileExt = strtolower(pathinfo($this->db, PATHINFO_EXTENSION));
    $fileName = "";
    if ($fileExt == "dbt") {
      $fileName = trim(substr($data, 8, 8));
      if (empty($fileName)) {
        $this->isBase3 = true;
      } else {
        $this->isBase4 = true;
      }
    }
    if ($this->isBase3) {
      $this->headers = [
        "freeblock_position" => unpack("L", substr($data, 0, 4))[1],
        "block_size" => 512
      ];
      return;
    }
    if ($this->isBase4) {
      $this->headers = [
        "freeblock_position" => unpack("L", substr($data, 0, 4))[1],
        "block_size" => unpack("S", substr($data, 20, 2))[1],
        "dbf-file" => $fileName
      ];
      return;
    }
    $this->headers = [
      "freeblock_position" => unpack("N", substr($data, 0, 4))[1],
      "block_size" => unpack("n", substr($data, 6, 2))[1]
    ];
  }

  private function readMemo($block) {
    fseek($this->fp, $this->headers["block_size"] * $block);
    if ($this->isBase3) {
      $text = "";
      while (!preg_match('/\x1a\x1a/', $text)) {
        $text .= fread($this->fp, 512);
      }
      $memo["text"] = $this->parseDBase3($text);
      return $memo;
    }
    $data = fread($this->fp, 8);
    if ($this->isBase4) {
      $memo = [
        "signature" => $this->signature[unpack("N", substr($data, 0, 4))[1]],
        "length" => octdec(intval(bin2hex(trim(substr($data, 4, 4)))))
      ];
      $memo["text"] = $this->parseDBase4(fread($this->fp, $memo["length"]));
      return $memo;
    }
    $memo = [
      "signature" => $this->signature[unpack("N", substr($data, 0, 4))[1]],
      "length" => unpack("N", substr($data, 4, 4))[1]
    ];
    $memo["text"] = fread($this->fp, $memo["length"]);
    return $memo;
  }

  private function parseDBase3($text) {
    if (preg_match('/\x1a\x1a/', $text, $matches, PREG_OFFSET_CAPTURE)) {
      $text = substr($text, 0, $matches[0][1]);
    }
    return $text;
  }

  private function parseDBase4($text) {
    if (preg_match('/\x0d\x0a/', $text, $matches, PREG_OFFSET_CAPTURE)) {
      $text = substr($text, 0, $matches[0][1]);
    }
    return preg_replace('/\x8d\x0a/', "\n", $text);
  }
}
