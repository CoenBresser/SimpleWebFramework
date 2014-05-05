<?php
/** 
 * v1.1 interfaces 
 * 
 * This version uses a better way of indexing the files on the server in a tree like manner: 
 * Section 
 * - Articles 
 * Works 
 * - Workgroups 
 *   ? Category 
 */

// Sections
$app->get('/v1.1/sections/', function () use ($app) { getV11File('sections.json', $app); });
$app->get('/v1.1/sections/:sectionId', function ($sectionId) use ($app) { getV11File('sections/'.$sectionId.'.json', $app); });

// Articles
$app->get('/v1.1/sections/:sectionId/articles', function ($sectionId) use ($app) { getV11File('sections/'.$sectionId.'/articles.json', $app); });

// Works
$app->get('/v1.1/works/', function () use ($app) { getV11File('works.json', $app); });
$app->get('/v1.1/works/:workGroup', function ($workGroup) use ($app) {
  // figure out the submitted parameters i.o. direct adressing
  $category = $app->request->get('category');
  
  // todo: use file_get_contents and json_decode to iterate through the works
  if ($category) {
    getV11File('works/'.$workGroup.'-'.$category.'.json', $app);
  } else {
    getV11File('works/'.$workGroup.'.json', $app);
}});

// Users
$app->get('/v1.1/users/', function () use ($app) { getV11File('users.json', $app); });
$app->get('/v1.1/login', function () use ($app) { logIn($app); });
$app->get('/v1.1/users/:userId', function ($userId) use ($app) { getV11File('users/'.$userId.'.json', $app); });

function getV11File($file, $app) {
  $file = 'db/1.1/' . $file;
  if (file_exists($file)) {
    include $file;
  } else {
    $app->notFound();  
}}

function logIn($app) {
  // login itself is done using autoriszation middleware
  // TODO: get the user based on the PHP_AUT_USER info and redirect to the user id
  $data = parseHttpDigest($_SERVER['PHP_AUTH_DIGEST']);
  if ( !$data ) {
    $app->halt(400, 'Not found!');
    return;
  }
  $app->redirect('/test/data/v1.1/users/'.$data['username']);
}

function parseHttpDigest( $headerValue ) {
  $needed_parts = array('username' => 1);
  $data = array();
  $keys = implode('|', array_keys($needed_parts));
  preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $headerValue, $matches, PREG_SET_ORDER);
  foreach ( $matches as $m ) {
      $data[$m[1]] = $m[3] ? $m[3] : $m[4];
      unset($needed_parts[$m[1]]);
  }
  return $needed_parts ? false : $data;
}
?>