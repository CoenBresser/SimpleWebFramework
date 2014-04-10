'use strict';

// Extension of the string prototype
if (typeof String.prototype.startsWith != 'function') {
  // see below for better implementation!
  String.prototype.startsWith = function (str){
    return this.indexOf(str) == 0;
  };
}

/* Controllers */

angular.module('myApp.controllers', []).
  controller('loginController', ['$scope', 'Section',
    function($scope, Section) {
      $scope.sections = Section.query();
    }]).
  // admin starting point, get al sections
  controller('AdminController', ['$scope', 'Section',
    function($scope, Section) {
      $scope.sections = Section.query();
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
      
      $element.html($scope.article.htmlContent);
      $scope.saveArticle = function() {
        $scope.article.$save({sectionId:$scope.section.id, articleId: $scope.article.id});
      };
    }]).
  controller('feedbackFormController', ['$scope', '$http',
    function($scope, $http) {
      $scope.submitted = false;
      
      $scope.fdata = {
        name: 'a',
        email: 'b@c.com',
        message: 'd',
        captcha: 'e'
      };
      
      $scope.submit = function () {
        console.debug($scope.fdata);
        $http.post('services/feedback.php', $scope.fdata)
          .success(function(){
            // Check for response
            
            $scope.submitted = true;
          });
      };
    }]).
    
  // We've got a section id from ngRoute, go and get the data
  controller('MainController', ['$scope', 'Section', '$routeParams', 
    function($scope, Section, $routeParams) {
      $scope.section = Section.get({sectionId: $routeParams.sectionId});
    }]).
  // Arrange the contents of the section
  controller('SectionController', ['$scope', 'Article', '$routeParams',
    function($scope, Article, $routeParams) {
      // We're in a section now ($scope.section is available)
      $scope.articles = Article.query({sectionId: $routeParams.sectionId});      
    }]).
  controller('BasicArticleController', ['$scope', '$location', '$window',
    function($scope, $location, $window) {
      // We're in an article now ($scope.article is available)
      
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
    }]).
  controller('GalleryController', ['$scope', 'Section', 'Works', '$routeParams', '$timeout', 
    function($scope, Section, Works, $routeParams, $timeout) {
      $scope.section = Section.get({sectionId: $routeParams.sectionId + '-gallery'});
      
      // Get the works, check for magic word 'all'
      var queryParams = {workGroup: $routeParams.sectionId};
      if ($routeParams.galleryId != 'all') {
        queryParams.category = $routeParams.galleryId;
      }
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