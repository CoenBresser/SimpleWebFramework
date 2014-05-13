<?php
/** 
 * v2.0 interfaces 
 * 
 * This version uses a better way of indexing the files on the server in a tree like manner: 
 * Section 
 * - Articles 
 * Works 
 * - Workgroups 
 *   ? Category 
 */

class v2_0_Data {
  const file_base = 'db/2.0/';
  protected $fileName;
  
  public function __construct($file) {
    // assume existence
    $this->fileName = $this::file_base . urlencode($file);
  }
  
  public static function dbExists($name) {
    $filename = v2_0_Data::file_base . urlencode($name);
    return file_exists($filename);
  }
  
  public static function deleteDb($name) {
    $filename = v2_0_Data::file_base . urlencode($name);
    $success = unlink($filename);
    // try without checking result
    if (file_exists($filename.'.lck')) {
      $success = $success && unlink($filename.'.lck');
    }
    return $success;
  }
  
  public function exists($key) {
    $dbh = dba_open($this->fileName, 'r');
    $value = dba_exists($key, $dbh);
    dba_close($dbh);
    return $value;
  }
  
  /**
   * Get a single value
   *
   * The database is closed when the function exits
   */
  public function getValue($key, $hidePassword = true) {
    $isUsersFile = ($this->fileName === v2_0_Data::file_base . urlencode('users'));
    $dbh = dba_open($this->fileName, 'r');
    $val = dba_fetch($key, $dbh);

    // Don't send passwords
    if ($hidePassword && $isUsersFile) {
      $eval = json_decode($val, true);
      unset($eval['password']);
      $val = json_encode($eval);
    }

    dba_close($dbh);
    return $val;
  }

  /**
   * Get all values
   *
   * The database is closed when the function exits
   */
  public function getValues($filter = null, $hidePassword = true) {
    $dbh = dba_open($this->fileName, 'rl');
    $isUsersFile = ($this->fileName === v2_0_Data::file_base . urlencode('users'));
    $jsonArray = '[';
    if ($the_key = dba_firstkey($dbh)) do {
      $val = dba_fetch($the_key, $dbh);

      // Don't send passwords
      if ($hidePassword && $isUsersFile) {
        $eval = json_decode($val, true);
        unset($eval['password']);
        $val = json_encode($eval);
      }
      
      // Check if we should filter. This could be optimized by splitting the loop in a filtered and non filtered loop
      // Not too big databases so skip above optimization
      if ($filter) {
        $eval = json_decode($val, true);
        foreach ($filter as $key => $value) {
          if (array_key_exists($key, $eval)) {
            $availableVal = $eval[$key];
            if (is_array($availableVal)) {
              if (in_array($value, $availableVal)) {
                $jsonArray = $jsonArray . $val . ',';
                break;
              }
            } else if ($availableVal == $value) {
              $jsonArray = $jsonArray . $val . ',';
              break;
            }
          }
        }
      } else {
        $jsonArray = $jsonArray . $val . ',';
      }
    } while ($the_key = dba_nextkey($dbh));
    dba_close($dbh);
    return rtrim($jsonArray, ",") . ']';
  }
  
  public function write($key, $value) {
    $dbh = dba_open($this->fileName, 'wl');
    $success = dba_insert($key, $value, $dbh);
    dba_close($dbh);
    return $success;
  }
  
  public function replace($key, $value) {
    $dbh = dba_open($this->fileName, 'wl');
    $success = dba_replace($key, $value, $dbh);
    dba_close($dbh);
    return $success;
  }
  
  public function delete($key) {
    $dbh = dba_open($this->fileName, 'wl');
    $success = dba_delete($key, $dbh);
    dba_close($dbh);
    return $success;
  }
  
  public static function checkCreateDb($name, $initialContentArray = '') {
    $filename = v2_0_Data::file_base . urlencode($name);
    if (!file_exists(v2_0_Data::file_base)) {
      mkdir(v2_0_Data::file_base, 0777, true);
    }
    if (!file_exists($filename)) {
      $dbh = false;
      $dbh = dba_open($filename, 'c');
      if ($initialContentArray !== '') {
        foreach ($initialContentArray as $key => $value) {
          dba_insert($key, $value, $dbh);
        }
      }
      dba_close($dbh);
    }
    return new v2_0_Data($name);
  }
}

interface iUserCredentials {
  const USER = 0;
  const SELF = 1; /* User needs to be himself to (e.g.) change user details) */
  const MEMBER = 2;
  const USER_ADMIN = 8;
  const SITE_ADMIN = 8;
  const DATA_ADMIN = 9;
  const ADMIN = 10;
  
  public function getUserRole($username);
  public function getUserPassword($username);
  public function userExists($username);
}

// TODO put passwords in an encrypted db and not in a retrievable json string
class CredentialDb implements iUserCredentials {
  
  protected $udb;
  
  public function __construct() {
    $this->udb = v2_0_Data::checkCreateDb('users', array('admin' => '{"id":"admin", "password":"joke2704", "role": "' . iUserCredentials::ADMIN . '"}'));
  }
  
  public function getUserRole($username) {
    $uInfo = json_decode($this->udb->getValue($username), true); 
    return $uInfo["role"];
  }
  
  public function getUserPassword($username) {
    $uInfo = json_decode($this->udb->getValue($username, false), true); 
    return $uInfo["password"];
  }
  
  public function userExists($username) {
    return $this->udb->exists($username);
  }
}

class v2_0_Interface {

  /**
   * Object of class HttpDigestAuth
   */
  protected $auth;
  protected $credDb;
  
  public function __construct($app) {
  
    $this->credDb = new CredentialDb();
    $this->auth = new HttpDigestAuth($app, $this->credDb);
    
    $helper = $this;
    $app->group('/v2.0', function () use ($app, $helper) {
      // Admin
      $app->get('/', function () use ($app, $helper) { $helper->doAdmin($app); });
      
      // Data paths
      $app->get('/:file(/(:id))', function ($file, $id=false) use ($app, $helper) { $helper->getFile($file, $id, $app); });
      $app->put('/:file(/(:id))', function ($file, $id=false) use ($app, $helper) { $helper->putFile($file, $id, $app); });
      $app->patch('/:file(/(:id))', function ($file, $id=false) use ($app, $helper) { $helper->patchFile($file, $id, $app); });
      $app->delete('/:file(/(:id))', function ($file, $id=false) use ($app, $helper) { $helper->deleteFile($file, $id, $app); });
    });
  }

  protected function requestHasVar($varName, $app) {
    return array_key_exists($varName, $app->request->get());
  }

  public function doAdmin($app) {
    $this->auth->assertUserRoleIsAtLeast(iUserCredentials::ADMIN);
    
    // Check for special requests
    if ($this->requestHasVar('dbList', $app)) {
      $files = scandir(v2_0_Data::file_base);
      $resp = "[";
      foreach ($files as $file) {
        if ($file === ".") { continue; }
        if ($file === "..") { continue; }
        if (Util::endsWith($file, ".lck")) { continue; }
        if (is_dir($file)) { continue; }
        $resp = $resp . '{"name":"' . $file . '"},';
      }
      echo rtrim($resp, ",") . ']';
    } else {
      // return the datafrontend application
      include 'v2.0_frontend.html';
    }
  }
  
  // check authorization (must be public due to helper...)
  public function checkAuth($file, $id, $app, $itemRole = iUserCredentials::MEMBER, $listRole = c) {
    // Checking existence could be done here, skip it as it could be used by 'someone' to see what exists or not
    if ($id) {
      $this->auth->assertUserRoleIsAtLeast($itemRole);
    } else {
      $this->auth->assertUserRoleIsAtLeast($listRole);
    }
    if ($file === 'users') {
      if ($id) {
        $this->auth->assertUserRoleIsIn(array(iUserCredentials::ADMIN, iUserCredentials::USER_ADMIN, iUserCredentials::SELF), $id);
      }
    }
    // TODO: file based checks
  }
  
  public function getFile($file, $id, $app) {
    $params = $app->request->params();
    $listAuth = $params ? iUserCredentials::USER : $listRole = iUserCredentials::SITE_ADMIN;
    $this->checkAuth($file, $id, $app, iUserCredentials::USER, $listAuth);
    
    try {
      $db = new v2_0_Data($file);
      if (!$id) {
        $params = $app->request->params();
        $app->response->body($db->getValues($params));
      } else {
        $app->response->body($db->getValue($id));
      }
    } catch (Exception $e) {
      $app->notFound();
    }
  }

  public function putFile($file, $id, $app) {
    $this->checkAuth($file, $id, $app, iUserCredentials::MEMBER, iUserCredentials::ADMIN); 
    
    if (!$id) {
      // creation of db
      if (!v2_0_Data::dbExists($file)) {
        v2_0_Data::checkCreateDb($file);
        $app->response->setStatus(201); // created
        $app->response->write($app->request->getBody());
      } else {
        $app->response->setStatus(409); // conflict
      }
      
      // check for body, if available then initial load
      $initContent = json_decode($app->request->getBody(), true);
      // TODO!!!
    } else {
      // individual put, check key existence
      if (!v2_0_Data::dbExists($file)) {
        $app->notFound();
      } else {
        $db = new v2_0_Data($file);
        if ($db->exists($id)) {
          $app->response->setStatus(409); // conflict
        } else {
          $content = $app->request->getBody();
          if ($content) {
            if ($db->write($id, $content)) {
              $app->response->setStatus(201); // created
              $app->response->write($content);
            } else {
              // unknown error
              $app->error();
            }
          } else {
            $app->response->setStatus(400); // bad request
          }
        }
      }
    }
  }

  public function patchFile($file, $id, $app) {
    $this->checkAuth($file, $id, $app, iUserCredentials::MEMBER, iUserCredentials::SITE_ADMIN); 
    
    if (!$id) {
      // series of patch
      $app->response->setStatus(405); // not implemented yet, method not allowed
    } else {
      // individual patch, check key existence
      if (!v2_0_Data::dbExists($file)) {
        $app->notFound();
      } else {
        $db = new v2_0_Data($file);
        if (!$db->exists($id)) {
          $app->response->setStatus(409); // try to patch non existent object
        } else {
          $content = $app->request->getBody();
          if ($content) {
            if ($db->replace($id, $content)) {
              $app->response->setStatus(202); // accepted
              $app->response->write($content);
            } else {
              // unknown error
              $app->error();
            }
          } else {
            $app->response->setStatus(400); // bad request
          }
        }
      }
    }
  }

  public function deleteFile($file, $id, $app) {
    $this->checkAuth($file, $id, $app, iUserCredentials::MEMBER, iUserCredentials::ADMIN); 
    
    if (!$id) {
      
      if (v2_0_Data::dbExists($file)) {
        if (v2_0_Data::deleteDb($file)) {
          $app->response->setStatus(204); // no content
        } else {
          // unknown error
          $app->error();
        }
      } else {
        $app->notFound(); // db not found
      }
    } else {
      if (!v2_0_Data::dbExists($file)) {
        $app->notFound();
      } else {
        $db = new v2_0_Data($file);
        if (!$db->exists($id)) {
          $app->notFound(); // nothing to delete
        } else {
          if ($db->delete($id, $content)) {
            $app->response->setStatus(204); // no content
          } else {
            // unknown error
            $app->error();
          }
        }
      }
    }
  }
}

class HttpDigestAuth {
  /**
   * The Slim app
   */
  protected $app;
  
  /**
   * @var array
   */
  protected $credentials;

  /**
   * @var string
   */
  protected $realm;

  /**
   * Constructor
   *
   * @param   iUserCredentials	$credentials	An object implementing iUserCredentials
   * @param   string  $realm      The HTTP Authentication realm
   * @return  void
   */
  public function __construct($app, iUserCredentials $credentials, $realm = 'Protected Area' ) {
    $this->app = $app;
    $this->credentials = $credentials;
    $this->realm = $realm;
  }

  public function assertUserRoleIsAtLeast($userRole) {
    if ($userRole === iUserCredentials::USER) {
      // Default level, no authorization necessary (and unwanted actually)
      return true;
    }
    if ($this->credentials->getUserRole($this->getUsername()) >= $userRole) {
      return true;
    }
    
    // No correct user
    // note, below function will halt if the user hits cancel so the end return statement should never be reached.
    $this->fail();
    return false;
  }
  
  public function assertUserRoleIsIn($roleArray, $reqUserName = false) {
    // check if any is iUserCredentials::USER
    foreach ($roleArray as $role) {
      if ($role === iUserCredentials::USER) {
        return true;
      }
    }

    // Get a user role
    $userName = $this->getUsername();
    $userRole = $this->credentials->getUserRole($userName);
    
    // Superadmin always allowed
    if ($userRole == iUserCredentials::ADMIN) {
      return true;
    }
    foreach ($roleArray as $role) {
      // ok if we have a match
      if ($role === $userRole) {
        return true;
      }
      
      // ok if the user is equal to the supplied
      if (($role === iUserCredentials::SELF) && ($userName === $reqUserName)) {
        return true;
      }
    }
    
    // No correct user
    // note, below function will halt if the user hits cancel so the end return statement should never be reached.
    $this->fail();
    return false;
  }
  
  /**
   * getUsername
   *
   * This method will check the HTTP request headers for previous authentication. If
   * the request has already authenticated, the next middleware is called. Otherwise,
   * a 401 Authentication Required response is returned to the client.
   *
   * @return void
   */
  public function getUsername() {
    //Check header and header username
    if ( empty($_SERVER['PHP_AUTH_DIGEST']) ) {
      $this->fail();
      return;
    } else {
      $data = $this->parseHttpDigest($_SERVER['PHP_AUTH_DIGEST']);
      if ( !$data || !$this->credentials->userExists($data['username']) ) {
          $this->fail();
          return;
      }
    }
    
    /*
    var_dump($data);
    echo PHP_EOL;
    echo $data['username'] . ':' . $this->realm . ':' . $this->credentials->getUserPassword($data['username']) . "<BR/>" . PHP_EOL;
    echo md5($data['username'] . ':' . $this->realm . ':' . $this->credentials->getUserPassword($data['username'])) . "<BR/>" . PHP_EOL;
    echo $_SERVER['REQUEST_METHOD'] . ':' . $data['uri'] . "<BR/>" . PHP_EOL;
    echo md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']) . "<BR/>" . PHP_EOL;
    echo $A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2 . "<BR/>" . PHP_EOL;
    echo md5($A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2) . "<BR/>" . PHP_EOL;
    echo $data['response'] . "<BR/>" . PHP_EOL;
    echo $this->credentials->getUserPassword($data['username']) . "<BR/>" . PHP_EOL;
    echo $this->credentials->getUserRole($data['username']) . "<BR/>" . PHP_EOL;
    return;
    */
    
    //Check header response
    $A1 = md5($data['username'] . ':' . $this->realm . ':' . $this->credentials->getUserPassword($data['username']));
    $A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
    $validResponse = md5($A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2);
    if ( $data['response'] !== $validResponse ) {
      $this->fail();
      return;
    }

    return $data['username'];
  }

  /**
   * Require Authentication from HTTP Client
   * The function halts the application if hit by cancel
   * @return void
   */
  protected function fail() {
    $this->app->response()->status(401);
    $this->app->response()->header('WWW-Authenticate', sprintf('Digest realm="%s",qop="auth",nonce="%s",opaque="%s"', $this->realm, uniqid(), md5($this->realm)));
    // TODO (or not): add stuff when the user hits cancel
    $this->app->stop();
  }

  /**
   * Parse HTTP Digest Authentication header
   *
   * @return array|false
   */
  protected function parseHttpDigest( $headerValue ) {
    $needed_parts = array('nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1);
    $data = array();
    $keys = implode('|', array_keys($needed_parts));
    preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $headerValue, $matches, PREG_SET_ORDER);
    foreach ( $matches as $m ) {
      $data[$m[1]] = $m[3] ? $m[3] : $m[4];
      unset($needed_parts[$m[1]]);
    }
    return $needed_parts ? false : $data;
  }
}

class Util {
  
  static function contains($haystack, $needle) {
    return strpos($haystack,$needle) !== false;
  }
  
  static function startsWith($haystack, $needle) {
    return $needle === "" || strpos($haystack, $needle) === 0;
  }
  
  static function endsWith($haystack, $needle) {
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
  }
}

// Build it
$v2_0_Interface = new v2_0_Interface($app);

?>