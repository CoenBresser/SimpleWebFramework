<?php

/**
 * Abstract class for Db handling
 */
abstract class AbstractDb {
  const DB_TYPE_JSON = "json";
  const DB_TYPE_BINARY = "binary";
  const DB_TYPE_CONFIG = "config";
  
  const file_base = 'db/';
  
  // Type of database, serves as base directory as well
  private $type;
  public $name;
  protected $location;
  
  protected function __construct($name, $type) {
    $this->type = $type;
    $this->name = $name;
    $this->location = AbstractDb::file_base . $this->type . '/' . urlencode($this->name);
    
    if (!file_exists(AbstractDb::file_base)) {
      mkdir(AbstractDb::file_base, 0777, true);
    }
    if (!file_exists(AbstractDb::file_base . $this->type . '/')) {
      mkdir(AbstractDb::file_base . $this->type . '/', 0777, true);
    }
  }

  public static function getHandlerClassname($type) {
    if ($type === AbstractDb::DB_TYPE_JSON) {
      return 'JsonDb';
    }
    if ($type === AbstractDb::DB_TYPE_BINARY) {
      return 'BinaryDb';
    }
    // TODO: binary, config (the last one needs to be abstracted...)
    return false;
  }
  
  public abstract function add($key, $value);
  public abstract function get($key);
  public abstract function contains($key);
  public abstract function getAll();
  public abstract function search($searchParams);
  public abstract function update($key, $value);
  public abstract function delete($key);
  public abstract function drop();
  public abstract function filter($data, $filterData);
  public abstract function writeDataForResponse($data);
  public abstract function getMimeType($data);
}

?>