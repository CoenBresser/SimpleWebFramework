<?php

require_once 'ServiceDb.php';
require_once 'UserDb.php';

class AuthenticationService {
  const DEFAULT_REALM = 'Protected Area';
  
  public static function hashPassword($username, $password, $realm = AuthenticationService::DEFAULT_REALM) {
    return md5($username . ':' . $realm . ':' . $password);
  }
  
  private static function parseHttpDigest( $headerValue ) {
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

  public static function requestHasCredentials($app) {
    return !empty($_SERVER['PHP_AUTH_DIGEST']);
  }
  
  public static function getUserRole($app, UserDb $userDb) {
    $data = AuthenticationService::parseHttpDigest($_SERVER['PHP_AUTH_DIGEST']);
    $userId = $data['username'];
    if (!$userDb->contains($userId)) {
      return Permissions::USER;
    }
    $user = $userDb->get($userId);

    //Check header response
    $A1 = $user->hash;
    $A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
    $validResponse = md5($A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2);
    if ( $data['response'] !== $validResponse ) {
      return Permissions::USER;
    }
    
    // All okay now
    return $user->role;
  }
  
  public static function requestNewUserCredentials($app, $realm = AuthenticationService::DEFAULT_REALM) {
    $app->response()->status(401);
    $app->response()->header('WWW-Authenticate', sprintf('Digest realm="%s",qop="auth",nonce="%s",opaque="%s"', $realm, uniqid(), md5($realm)));
    // TODO (or not): add stuff when the user hits cancel
    $app->stop();
    // Just in case
    die();
  }
}

?>