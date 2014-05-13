'use strict';


// Declare app level module which depends on filters, and services
angular.module('myApp', [
  'ngResource',
  'ngRoute',
  'myApp.filters',
  'myApp.services',
  'myApp.directives',
  'myApp.controllers',
  'ngSanitize' /*,
  'ngAnimate' */
]).
config(['$routeProvider', function($routeProvider) {
  $routeProvider.when('/admin', {templateUrl: 'partials/admin.html', controller: 'AdminController'});
  $routeProvider.when('/:sectionId', {templateUrl: 'partials/section.html', controller: 'MainController'});
  // todo: change to '/:workGroup/:category' for simplifying the services
  $routeProvider.when('/:sectionId/:galleryId', {templateUrl: 'partials/section.html', controller: 'MainController'});
  $routeProvider.otherwise({redirectTo: '/welcome'}); // always the main section 
}]);
