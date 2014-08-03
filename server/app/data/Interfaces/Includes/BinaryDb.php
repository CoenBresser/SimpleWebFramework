<?php
require_once 'JsonDb.php';

class BinaryObject {
  public $fileLocation;
  public $mimeType;
  public $id;
}

// TODO: abstract from JsonDb for easy addition of administration objects
class BinaryDb extends JsonDb {
  private $filesLocation;
  
  public function __construct($dbName, $type = AbstractDb::DB_TYPE_BINARY) {
    parent::__construct($dbName, $type);
    $this->filesLocation = $this->location . '-files';
    if (!file_exists($this->filesLocation)) {
      mkdir($this->filesLocation);
    }
  }
  
  public function add($key, $value = null) {
    if (empty($_FILES) or empty ($_FILES['file'])) {
      return false;
    }
    if ($_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
      return false;
    }
    $obj = new BinaryObject();
    if (!$value) {
      // There might be post values in the key as this call comes from outside
      foreach ($key as $prop => $val) {
        $obj->{$prop} = $val;
      }
    }
    
    $obj->mimeType = $_FILES['file']['type'];
    if (!isset($obj->id)) {
      $obj->id = $_FILES['file']['name'];
    }
    $now = new DateTime();
    $now = $now->format('Y-m-d H:i:s');
    $obj->fileLocation = $this->filesLocation . '/' . $obj->id . '-' . $now . '-' . $_FILES['file']['name'];

    // move the object
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $obj->fileLocation)) {
      return false;
    }
    
    // if a key is duplicate, this will fail and the image will have been copied. Fix this in a maintenance task
    return parent::add($obj);
  }
  
  public function update($key, $value) {
  }
  
  /* Delete is done only in the JSON db. In maintenance remove the files
  public function delete($key) {
    return parent::delete($key);
  } */

  private function delTree($dir) { 
    $files = array_diff(scandir($dir), array('.','..')); 

    foreach ($files as $file) { 
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
    }

    return rmdir($dir); 
  }
    
  public function drop() {
    if (parent::drop()) {
      return $this->delTree($this->filesLocation);
    } else {
      return false;
    }
  }
  
  public function writeDataForResponse($data) {
    if (is_array($data)) {
      return parent::writeDataForResponse($data);
    } else {
      return file_get_contents($data->fileLocation);
    }
  }
  
  public function getMimeType($data) {
    if (is_array($data)) {
      return parent::getMimeType($data);
    } else {
      return $data->mimeType;
    }
  }
}

?>