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
  $category = $app->request->get('category');
  if ($category) {
    getV11File('works/'.$workGroup.'-'.$category.'.json', $app);
  } else {
    getV11File('works/'.$workGroup.'.json', $app);
}});

function getV11File($file, $app) {
  $file = 'db/1.1/' . $file;
  if (file_exists($file)) {
    include $file;
  } else {
    $app->notFound();  
}}

?>