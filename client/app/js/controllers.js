'use strict';

function arr_delete(item) {
  for(var i = this.length; i--;) {
    if(this[i] === item) {
      this.splice(i, 1);
    }
  }
}
if (typeof Array.prototype.delete === 'undefined') {
  Array.prototype.delete = arr_delete;
}
function str_starts_with(string) {
  return this.slice(0, string.length) == string;
}
if (typeof String.prototype.startsWith != 'function') {
  String.prototype.startsWith = str_starts_with;
}
function str_ends_with(string) {
  return this.slice(-string.length) == string;
}
if (typeof String.prototype.endsWith != 'function') {
  String.prototype.endsWith = str_ends_with;
}

// Extension of the string prototype
if (typeof String.prototype.startsWith != 'function') {
  // see below for better implementation!
  String.prototype.startsWith = function (str){
    return this.indexOf(str) == 0;
  };
}

/* Admin controllers */
angular.module('myApp.controllers', []).
  controller('configurationController', function($scope, $location, $http, currentUser) {
      $scope.currentUser = currentUser;
      $scope.login = function() {
        $location.path('admin');
        /*hue test:
        $http.put('http://192.168.50.102/api/newdeveloper/groups/0/action', { on: true });*/
      };
    }).
  // admin starting point, get al sections
  controller('AdminController', function($scope, Section) {
      // get a user
      $scope.sections = Section.query();
      
      // Used for linking sections and articles
      $scope.incomingSectionLinks = new Array();
    }).
  // admin section content controller
  controller('AdminSectionController', function($scope, Article) {
      
      // We're in a section now ($scope.section is available)
      $scope.articles = Article.query({sectionId: $scope.section.id});
      
      $scope.hasIncomingLink = function() {
        if ($scope.section.id === 'welcome') {
          // Skip the welcome section as this will always be the start
          return true;
        }
        // TODO: more than 1 article can link to a section
        return $scope.incomingSectionLinks[$scope.section.id];
      }
      
      $scope.deleteArticle = function(list, item, confirmText) {
        if (!confirmText || confirm(confirmText)) {
          item.$delete(function (response) {
            list.delete(item);
            if (item.link && !item.link.startsWith('http://')) {
              var link = (item.link.indexOf('/') > 1) ? 'gallery' : item.link;
              $scope.incomingSectionLinks[link] = $scope.incomingSectionLinks[link] - 1;
              
              if ($scope.incomingSectionLinks[link] == 0) {
                $scope.incomingSectionLinks.delete(link);
              }
            }
          });
        }
      }
      
      $scope.deleteSection = function(list, item, confirmText) {
        if (item.id === 'welcome') {
          console.warn('Trying to delete first section');
          return;
        }
        if (confirm(confirmText)) {
          item.$delete(function (response) {
            angular.forEach($scope.articles, function (article, key) {
              $scope.deleteArticle($scope.articles, article);
            });
            list.delete(item);
          });
        }
      }
    }).
  // admin article content controller
  controller('AdminArticleController', function($scope, Article) {
      // We're in an article now
      if (!$scope.article.partialUrl) {
        $scope.article.partialUrl = "partials/basicArticle.html"
      }
      $scope.deriveStyle = function(articleStyle) {
        return {
          width: articleStyle.width,
          height: articleStyle.height,
          left: articleStyle.left,
          top: articleStyle.top
        };
      }
      if ($scope.article.link && !$scope.article.link.startsWith('http://')) {
        // Quick and dirty check on galleries
        var link = ($scope.article.link.indexOf('/') > 1) ? 'gallery' : $scope.article.link;
        $scope.incomingSectionLinks[link] = $scope.incomingSectionLinks[link] ? $scope.incomingSectionLinks[link] + 1 : 1;
      }
      $scope.moveOnDrag = function(article, $event) {
        article.style.left = +article.style.left.split('px')[0] + $event.tickX + 'px';
        article.style.top = +article.style.top.split('px')[0] + $event.tickY + 'px';
      }
      $scope.resizeOnDrag = function(article, $event) {
        article.style.width = Math.max(140, (+article.style.width.split('px')[0] + $event.tickX)) + 'px';
        article.style.height = Math.max(140, (+article.style.height.split('px')[0] + $event.tickY)) + 'px';
      }
    }).
    
/* Normal controllers */
  // We've got a section id from ngRoute, go and get the data
  controller('MainController', function($scope, Section, $routeParams) {
      var sectionId = ($routeParams.galleryId) ? 'gallery' : $routeParams.sectionId;
      $scope.section = Section.get({sectionId: sectionId});
    }).
    
  // Arrange the contents of the section
  controller('SectionController', function($scope, Article, $routeParams) {
      // Get the articles to build up the gallery
      var sectionId = ($routeParams.galleryId) ? 'gallery' : $routeParams.sectionId;
      $scope.articles = Article.query({sectionId: sectionId});
    }).
    
  controller('ArticleController', function($scope, $location, $window) {
      // We're in an article now ($scope.article is available)
      if (!$scope.article.partialUrl) {
        $scope.article.partialUrl = "partials/basicArticle.html";
      }
      $scope.handleClick = function() {
        if ($scope.article.link) {
          if ($scope.article.link.startsWith('http://')) {
            $window.location.href = $scope.article.link;
          } else {
            $location.path($scope.article.link);
          }
        } else {
          console.debug('No action defined for article ' + $scope.article.id);
        }
      };
      if ($scope.article.link) {
        // Add the style
        $scope.article.style.cursor = "pointer";
      }
    }).

  controller('feedbackFormController', function($scope, $http) {
      $scope.submitted = false;
      
      $scope.submit = function () {
        console.debug($scope.fdata);
        $http.post('services/feedback.php', $scope.fdata)
          .success(function(){
            // TODO: Check for response (errors are responded as errors by the service)
            $scope.submitted = true;
          });
      };
    }).

  controller('GalleryController', function($scope, Works, $routeParams, $timeout) {
      if (!$routeParams.galleryId) {
        console.warn('No gallery id set. Quitting.');
        return;
      }
      
      // Get the works, category all is used to get all images
      var queryParams = {workGroup: $routeParams.sectionId, category: $routeParams.galleryId};
      $scope.works = Works.query(queryParams);
      
      // initial image index
      $scope._Index = 0;

      // if a current image is the same as requested image
      $scope.isActive = function (index) {
          return $scope._Index === index;
      };

      // show prev image
      $scope.showPrev = function () {
          $scope._Index = ($scope._Index > 0) ? --$scope._Index : $scope.works.length - 1;
      };

      // show next image
      $scope.showNext = function () {
          $scope._Index = ($scope._Index < $scope.works.length - 1) ? ++$scope._Index : 0;
      };

      // show a certain image
      $scope.showWork = function (index) {
          $scope._Index = index;
      };
      
      $scope.interval = 3000;
      $scope.isRunning = true;
      
      var moveNext = function() {
        if ($scope.isRunning) {
          $scope.showNext();
          $timeout(moveNext, $scope.interval);
        }
      }
      $timeout(moveNext, $scope.interval);
      
    });