<?php

/**
 * v1.0 interfaces
 *
 * This version uses a better way of indexing the files on the server in a tree like manner:
 * Section
 * - Articles
 * Works
 * - Workgroups
 *   ? Category
 */

// Sections
$app->get('/v1.0/sections/', function () use ($app) { getV10File('oldJSON/sections/all.json', $app); });
$app->get('/v1.0/sections/:sectionId', function ($sectionId) use ($app) { getV10File('oldJSON/sections/'.$sectionId.'.json', $app); });

// Articles
$app->get('/v1.0/sections/:sectionId/articles', function ($sectionId) use ($app) { getV10File('oldJSON/articles/'.$sectionId.'-all.json', $app); });
$app->get('/v1.0/sections/:sectionId/articles/articleId', function ($sectionId, $articleId) use ($app) { getV10File('oldJSON/articles/'.$sectionId.'-'.$articleId.'.json', $app); });

// Works
$app->get('/v1.0/works/:workGroup', function ($workGroup) use ($app) {
  $category = $app->request->get('category');
  if ($category) {
    getV10File('oldJSON/works/'.$workGroup.'-'.$category.'.json', $app);
  } else {
    getV10File('oldJSON/works/'.$workGroup.'-all.json', $app);
  }
});

// Partials
$app->get('/v1.0/partials/:partialId', function ($partialId) use ($app) { getV10File('oldJSON/partials/'.$partialId.'.json', $app); });


function getV10File($file, $app) {
  if (file_exists($file)) {
    include $file;
  } else {
    $app->notFound();
  }
}

?>