<?php
require_once 'JsonDb.php';
require_once 'ServiceDb.php';
require_once 'AuthenticationService.php';

class User {
  public $role;
  public $hash;
  public $id;  

  public function __construct($id, $role, $password) {
    $this->id = $id;
    $this->role = $role;
    $this->hash = AuthenticationService::hashPassword($id, $password);
  }
}

class UserDb extends JsonDb {
  
  public function __construct() {
    parent::__construct('users');
  }
  
  public function add($key, $value = null) {
    if (!$value) {
      // Try to construct the value from the key parameter
      if ($key instanceof User) {
        $value = $key;
        $key = $value->id;
      } else {
        // new user from json, check first
        $data = json_decode($key, true);
        
        if (!isset($data['id']) or !isset($data['password'])) {
          return false;
        }
        if ($this->contains($data['id'])) {
          return false;
        }
        
        $role = isset($data['role']) ? $data['role'] : Permissions::USER;
        $value = new User($data['id'], $role, $data['password']);
        unset($data['id']);
        unset($data['password']);
        unset($data['role']);
        
        // now for the rest of the data
        foreach ($data as $prop => $val) {
          $value->{$prop} = $val;
        }
        $key = $value->id;
      }
    }
    return parent::add($key, $value);
  }
  
  public function update($key, $value) {
    // TODO: transform data (password, hash, etc);
    $user = parent::get($key);
    $updated = json_decode($value, true);
    
    if (array_key_exists('hash', $updated)) {
      // Don't allow changes in hash
      return false;
    }
    if (array_key_exists('password', $updated)) {
      $updated['hash'] = AuthenticationService::hashPassword($key, $updated['password']);
      unset($updated['password']);
    }
    foreach ($updated as $prop => $val) {
      $user->{$prop} = $val;
    }
    // Note that above allows skipping of properties and addition of other properties as well and removal of properties is omitted
    return parent::update($key, $user);
  }
}

?>