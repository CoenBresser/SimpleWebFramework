<?php
require_once 'AbstractDb.php';

class JsonObject {
}

class JsonDb extends AbstractDb {
  
  public function __construct($dbName, $type = AbstractDb::DB_TYPE_JSON) {
    parent::__construct($dbName, $type);
    if (!file_exists($this->location)) {
      $dbh = dba_open($this->location, 'c');
      dba_close($dbh);
    }
  }
  

  public function add($key, $value = null) {
    if (!$value) {
      if (gettype($key) === "string") {
        $value = json_decode($key);
      } else {
        $value = $key;
      }
      if (!$value->id) {
        return false;
      }
      //TODO: check and correct id ending with a space
      $key = $value->id;
    }
    if (gettype($value) === "string") {
      $value = json_decode($value);
    }
    
    $dbh = dba_open($this->location, 'wl');
    $success = dba_insert($key, serialize($value), $dbh);
    dba_close($dbh);
    return $success ? $key : false;
  }
  
  public function get($key) {
    $dbh = dba_open($this->location, 'r');
    $val = unserialize(dba_fetch($key, $dbh));
    dba_close($dbh);
    return $val;
  }
  
  public function contains($key) {
    $dbh = dba_open($this->location, 'r');
    $value = dba_exists($key, $dbh);
    dba_close($dbh);
    return $value;
  }
  
  // TODO: this might result in memory problems if the DB gets too big. If so, a streaming method should be constructed
  public function getAll() {
    $all = array();
    $dbh = dba_open($this->location, 'rl');
    if ($the_key = dba_firstkey($dbh)) do {
      //$all[$the_key] = unserialize(dba_fetch($the_key, $dbh)); This way an index is added and the return object is not formatted as array
      $all[] = unserialize(dba_fetch($the_key, $dbh));
    } while ($the_key = dba_nextkey($dbh));
    dba_close($dbh);
    return $all;
  }
  
  public function search($searchParams) {
    $all = array();
    $dbh = dba_open($this->location, 'rl');
    if ($the_key = dba_firstkey($dbh)) do {
      $obj = unserialize(dba_fetch($the_key, $dbh));
      $keep = true;
      foreach ($searchParams as $prop => $val) {
        if (!isset($obj, $prop) or ($obj->{$prop} !== $val)) {
          $keep = false;
          break;
        }
      }
      if ($keep) {
        $all[] = $obj;
      }
    } while ($the_key = dba_nextkey($dbh));
    dba_close($dbh);
    return $all;
  }
  
  public function update($key, $value) {
    if (gettype($value) === "string") {
      $value = json_decode($value);
    }
    
    $dbh = dba_open($this->location, 'wl');
    $success = dba_replace($key, serialize($value), $dbh);
    dba_close($dbh);
    return $success ? $value : false;
  }
  
  public function delete($key) {
    $dbh = dba_open($this->location, 'wl');
    $success = dba_delete($key, $dbh);
    dba_close($dbh);
    return $success;    
  }
  
  public function drop() {
    $success = unlink($this->location);
    // try without checking result
    if (file_exists($this->location.'.lck')) {
      $success = $success && unlink($this->location.'.lck');
    }
    return $success;
  }
  
    private function filterSingleValue($value, $filterData) {
    foreach ($filterData as $filter) {
      unset($value->{$filter});
    }
    return $value;
  }
  
  public function filter($data, $filterData) {
    if (is_array($data)) {
      $vals = array();
      foreach ($data as $value) {
        $vals[] = $this->filterSingleValue($value, $filterData);
      }
      return $vals;
    } else {
      return $this->filterSingleValue($data, $filterData);
    }
  }
  
  public function writeDataForResponse($data) {
    return json_encode($data);
  }
  
  public function getMimeType($data) {
    return 'application/json';
  }
}

?>