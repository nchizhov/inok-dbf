## DBF-file Reader Class

[![License](https://poser.pugx.org/inok/dbf/license)](https://packagist.org/packages/inok/dbf)
[![License](https://poser.pugx.org/inok/dbf/v/stable)](https://packagist.org/packages/inok/dbf)
[![License](https://poser.pugx.org/inok/dbf/d/monthly)](https://packagist.org/packages/inok/dbf)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nchizhov/inok-dbf/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nchizhov/inok-dbf/?branch=master)

### Description
This group of classes (**Table**, **Records**, **Memo** in namespace **Inok\Dbf**) needs for reading DBF-files (structure, records) with/without MEMO-fields.

### Classes descriptions
#### Table
May read headers of: FoxBASE, dBASE III, dBASE IV, dBASE 5, dBASE 7 (*partial*), FoxPro, FoxBASE+, Visual FoxPro file structure.

##### Using: 
```php
$table = new \Inok\Dbf\Table(/path/to/dbf/file, $charset);
```
where `$charset` only using, when charset in dbf-file not defined (default charset: **866**)

##### Methods:
   * ```$table->getHeaders()``` - return array of DBF-file headers
   * ```$table->getColumns()``` - return array of DBF-file columns
   * ```$table->getData()``` - return resource to DBF-file body (required for **Inok\Dbf\Records**)
   * ```$table->error``` - return boolean true if in DBF-file errors in headers or columns
   * ```$table->error_info``` - returns error description or **null** - if no errors
   * ```$table->close()``` - close DBF-file (also closing on destruct class)
      
#### Records
May read records of: FoxBASE, dBASE III, dBASE IV, dBASE 5, dBASE 7, FoxPro, FoxBASE+, Visual FoxPro file records. Now implements column types:
* **C** - Character
* **D** - Date as string in format 'YYYYMMDD' (if empty converts to null)
* **F** - Float (if empty converts to null)
* **G** - General (OLE)
* **L** - Logical ('t', 'y', 'д' - converts to 1, '?' - converts to null, all others to 0)
* **M** - Memo 
* **N** - Numeric (if empty converts to null)
* **P** - Picture
* **T** - DateTime as string in format 'YYYYMMDDHHIISS' (if empty converts to null)
* **@** - DateTime as string in format 'YYYYMMDDHHIISS' (if empty converts to null)
* **I** - Integer
* **Y** - Currency
* **0** - NullFlags as integer

Not supported types:
* **B**:
  - dBase 5 (Binary) - block num in MEMO-file (10 digits, padded right with spaces). Empty value: 10 spaces
  - Visual FoxPro (Double) - float 8-byte binary format [IEEE 754](https://ru.wikipedia.org/wiki/IEEE_754). Empty values - zero
* **O**:
  - dBase 7 (Double) - float 8-byte format [IEEE 754](https://ru.wikipedia.org/wiki/IEEE_754). Bytes inverse, for negative numbers - inverse all bits, for positive - sign bit only. Empty values - zero
* **Q**:
  - Visual FoxPro (Varbinary) - Binary data with variable data. Start part saved in DBF-file, other part with variable lenth - in MEMO-file
* **V**:
  - Visual FoxPro (Varchar) - String with variable length. Start part saved in DBS-file, other part with variable length - in MEMO-file.
* **W**
  - Visual FoxPro (Blob) - No info about format
* **+**
  - dBase 7 (Autoincrement) - Signed integer in binary format. Length - 4 bytes BE.

##### Using: 
```php
$records = new \Inok\Dbf\Records($data, $encode, $headers, $columns);
```
* **$data** - Instance of Table class or DBF-file resource from Inok\Dbf\Table getData()
* **$encode** - iconv **Memo, Character** fields to selected character (default: **utf8**)
* **$headers** - DBF-file headers array or null if $data is instance of Table class (default: null)
* **$columns** - DBF-file columns array or null if $data is instance of Table class (default: null)

##### Methods:
   * ```$record->nextRecord``` - reads next record from DBF-file (return record-array or false - if records finished)
   
#### Memo
May read MEMO-files formats (headers and records): DBT, FPT, SMT

##### Using:
```php
$memo = new \Inok\Dbf\Memo(/path/to/dbf/memo/file);
```

##### Methods:
   * ```$memo->getHeaders()``` - returns array of MEMO-file headers
   * ```$memo->readMemo($record)``` - return array of MEMO ```$record``` position
   * ```$memo->close()``` - close MEMO-file (also closing on destruct class)
   
### Notes

##### Table header array:
* **dbf_file** - path to DBF-file
* **table** - DBF-table name in lowercase
* **version** - DBF-file version
* **version_name** - DBF-file version text description
* **date** - DBF-file last change date in *d.m.Y*-format (years between 1970 - 2069)
* **records** - Number of records in DBF-file
* **record_length** - One record length (in bytes) in DBF-file
* **unfinished_transaction** - Flag of unfinished transactions in DBF-file
* **coded** - Flag of coded *dBASE IV* database
* **mdx_flag** - Flag of index MDX-file
* **charset** - Charset identifier of DBF-file records
* **charset_name** - Normal charset name of DBF-file records
* **memo** - If *True* - DBF-file have MEMO-fields
* **memo_file** - MEMO-file path if DBF-file have MEMO-fields

##### Column header array:
* **name** - column name in lowercase
* **type** - column type (one char)
* **length** - column length
* **decimal** - if not *0* - decimal part of number
* **mdx_flag** - MDX-flag on column (except *Visual FoxPro*)
* **auto_increment** - next auto increment value (only for *dBASE 7*, *Visual FoxPro*)
* **system** - column is system (only for *Visual FoxPro*)
* **has_null** - column can story null values (only for *Visual FoxPro*)
* **binary** - column for *CHAR* and *MEMO* only (only for *Visual FoxPro*)
* **auto_increment_next** - next value for auto increment (only for *Visual FoxPro*)
* **auto_increment_step** - step value for auto increment (only for *Visual FoxPro*)

##### MEMO-file header array:
* **freeblock_position** - position of next free block of MEMO-file
* **block_size** - MEMO-file block size (*if exists*)
* **dbf-file** - DBF-file name (*if exists*)

##### MEMO-record header array:
* **signature** - type of MEMO-record: text or template (*if exists*)
* **length** - size of MEMO-record (*if exists*)
* **text** - text of MEMO-record

### License

This package is released under the __MIT license__. 
