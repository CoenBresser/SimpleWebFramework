<?php
require_once 'AbstractDb.php';
require_once 'JsonDb.php';
require_once 'BinaryDb.php';

/**
 * Configuration items for the interface services
 *
 * Configuration is like:
[{ 
    "id":"users",
    "type":"json",
    "storage": "",
    "permissions": {
      "list":"",
      "item":"",
      "filtered":""
    },
    "hide":["hash"]
  },
  {
    "id":"images",
    "type":"binary",
    "storage": "",
    "permissions": {
      "list":"",
      "item":"",
      "filtered":""
    }  
}]
 
 read, list, search, write 
 */

/**
 * Class handling the the permissions
 */
class Permissions {
  const USER = 0;
  //const SELF = -1; //TODO User may be himself to (e.g.) change user details) 
  const MEMBER = 2;
  const USER_ADMIN = 8;
  const SITE_ADMIN = 8;
  const DATA_ADMIN = 9;
  const ADMIN = 10;
  const NO_ONE = 20;
  
  public $read;
  public $list;
  public $search;
  public $write; // consider splitting in update, create, delete
  
  public function __construct($list, $read, $search, $write) {
    $this->list = $list;
    $this->read = $read;
    $this->search = $search;
    $this->write = $write;
  }
}

/**
 * Actual configuration
 */
class ServiceConfig {
  
  public $id;
  public $type;
  public $storage;
  public $permissions;
  public $hide;
  public $handlerClassname;
  
  // Consider adding a prototype object (for JSON db's)
  
  public function __construct($id, $type, $storage, Permissions $permissions, array $hide, $handlerClassname) {
    $this->id = $id;
    $this->type = $type;
    $this->storage = $storage;
    $this->permissions = $permissions;
    $this->hide = $hide;
    $this->handlerClassname = $handlerClassname;
  }
}

class ServiceDb extends JsonDb {

  public function __construct() {
    parent::__construct('serviceConfig', AbstractDb::DB_TYPE_CONFIG);
  }
  
  public function add($key, $value = null) {
    if (!$value) {
      // Try to construct the value from the key parameter
      if ($key instanceof ServiceConfig) {
        $value = $key;
        $key = $value->id;
      } else {
        // new ServiceConfig from json, check first
        $data = json_decode($key, true);
        
        if (!isset($data['id']) or !isset($data['type']) or !isset($data['permissions'])) {
          return false;
        }
        if (!isset($data['permissions']['read']) 
            or !isset($data['permissions']['list']) 
            or !isset($data['permissions']['search']) 
            or !isset($data['permissions']['write'])) {
          return false;
        }
        if ($this->contains($data['id'])) {
          return false;
        }
        
        $handlerClassname = AbstractDb::getHandlerClassname($data['type']);
        if (!$handlerClassname) {
          return false;
        }
        // Create it
        $db = new $handlerClassname($data['id']);
        if (!$db) {
          return false;
        }
        $perms = new Permissions($data['permissions']['list'], $data['permissions']['read'], $data['permissions']['search'], $data['permissions']['write']);
        $value = new ServiceConfig($data['id'], $data['type'], storage, $perms, array(), $handlerClassname);
        
        unset($data['id']);
        unset($data['type']);
        unset($data['permissions']);
        
        // now for the rest of the data
        foreach ($data as $prop => $val) {
          $value->{$prop} = $val;
        }
        $key = $value->id;
        echo $key . ": " . json_encode($value);
      }
    }
    return parent::add($key, $value);
  }
}

?>