<?php
/********************************************
 * DBF-file MEMO-fields Reader
 *
 * Author: Chizhov Nikolay <admin@kgd.in>
 * (c) 2016 CIOB "Inok"
 ********************************************/

namespace Inok\Dbf;

class Memo {
  private $headers = null;

  private $db, $fp;
  private $signature = [
    0 => "template|picture",
    1 => "text"
  ];

  public function __construct($file) {
    $this->db = $file;

    $this->open();
  }

  public function __destruct() {
    $this->close();
  }

  private function open() {
    if (!file_exists($this->db)) {
      throw new \Exception(sprintf('Memo-file %s cannot be found', $this->db));
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
    $this->headers = [
      "freeblock_position" => unpack("N", substr($data, 0, 4))[1],
      "block_size" => unpack("n", substr($data, 6, 2))[1]
    ];
  }

  private function readMemo($block) {
    fseek($this->fp, $this->headers["block_size"] * $block);
    $data = fread($this->fp, 8);
    $memo = [
      "signature" => $this->signature[unpack("N", substr($data, 0, 4))[1]],
      "length" => unpack("N", substr($data, 4, 4))[1]
    ];
    $memo["text"] = fread($this->fp, $memo["length"]);
    return $memo;
  }
}