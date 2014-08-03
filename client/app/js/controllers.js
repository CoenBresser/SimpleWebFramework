'use strict';

// Extension of the string prototype
if (typeof String.prototype.startsWith != 'function') {
  // see below for better implementation!
  String.prototype.startsWith = function (str){
    return this.indexOf(str) == 0;
  };
}

/* Admin controllers */
angular.module('myApp.controllers', []).
  controller('configurationController', ['$scope', '$location', '$http',
    function($scope, $location, $http) {
      $scope.login = function() {
        $scope.user = true;
        /*hue test:
        $http.put('http://192.168.50.102/api/newdeveloper/groups/0/action', { on: true });*/
      };
    }]).
  controller('loginController', ['$scope', '$location',
    function($scope, $location) {
      $scope.handleClick = function() {
        if (!$scope.user) {
          $location.path('admin');
        } else {
          $location.path('');
        }
      };
    }]).
  // admin starting point, get al sections
  controller('AdminController', ['$scope', 'Section',
    function($scope, Section) {
      // get a user
      //$scope.sections = Section.query();
    }]).
  // admin section content controller
  controller('AdminSectionController', ['$scope', 'Article',
    function($scope, Article) {
      // We're in a section now ($scope.section is available)
      $scope.articles = Article.query({sectionId: $scope.section.id});
    }]).
  // admin article content controller
  controller('AdminArticleController', ['$scope', 'Section', 'Article', '$routeParams', '$location', '$window', '$element',
    function($scope, Section, Article, $routeParams, $location, $window, $element) {
      // We're in an article now
      if (!$scope.article.partialUrl) {
        $scope.article.partialUrl = "partials/basicArticle.html"
      }
      
      $element.html($scope.article.htmlContent);
      $scope.saveArticle = function() {
        $scope.article.$save({sectionId:$scope.section.id, articleId: $scope.article.id});
      };
    }]).
    
/* Normal controllers */
  // We've got a section id from ngRoute, go and get the data
  controller('MainController', ['$scope', 'Section', '$routeParams', 
    function($scope, Section, $routeParams) {
      var sectionId = ($routeParams.galleryId) ? 'gallery' : $routeParams.sectionId;
      console.debug(sectionId);
      $scope.section = Section.get({sectionId: sectionId});
    }]).
    
  // Arrange the contents of the section
  controller('SectionController', ['$scope', 'Article', '$routeParams',
    function($scope, Article, $routeParams) {
      // Get the articles to build up the gallery
      var sectionId = ($routeParams.galleryId) ? 'gallery' : $routeParams.sectionId;
      console.debug(sectionId);
      $scope.articles = Article.query({sectionId: sectionId});
    }]).
    
  controller('ArticleController', ['$scope', '$location', '$window',
    function($scope, $location, $window) {
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
    }]).

  controller('feedbackFormController', ['$scope', '$http',
    function($scope, $http) {
      $scope.submitted = false;
      
      $scope.submit = function () {
        console.debug($scope.fdata);
        $http.post('services/feedback.php', $scope.fdata)
          .success(function(){
            // TODO: Check for response (errors are responded as errors by the service)
            $scope.submitted = true;
          });
      };
    }]).

  controller('GalleryController', ['$scope', 'Works', '$routeParams', '$timeout', 
    function($scope, Works, $routeParams, $timeout) {
      
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
      
    }]);