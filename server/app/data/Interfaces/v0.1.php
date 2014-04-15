<?php

/**
 * v0.1 interface methods. 
 *
 * This version uses direct indexing to the file
 */
$app->get('/v0.1/sections', function () use ($app) { getV01File('oldJSON/sections/all.json', $app); });
$app->get('/v0.1/sections/:sectionId', function ($sectionId) use ($app) { getV01File('oldJSON/sections/'.$sectionId, $app); });
$app->get('/v0.1/articles/:articleId', function ($articleId) use ($app) { getV01File('oldJSON/articles/'.$articleId, $app); });
$app->get('/v0.1/works/:workGroup', function ($workGroup) use ($app) { getV01File('oldJSON/works/'.$workGroup, $app); });

function getV01File($file, $app) {
  if (file_exists($file)) {
    include $file;
  } else {
    $app->notFound();
  }
}

?>