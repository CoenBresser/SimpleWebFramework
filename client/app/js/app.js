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
config(['$routeProvider', '$httpProvider', function($routeProvider, $httpProvider) {
  
  $routeProvider.when('/admin', {templateUrl: 'partials/admin.html', controller: 'AdminController'});
  $routeProvider.when('/:sectionId', {templateUrl: 'partials/section.html', controller: 'MainController'});
  // todo: change to '/:workGroup/:category' for simplifying the services
  $routeProvider.when('/:sectionId/:galleryId', {templateUrl: 'partials/section.html', controller: 'MainController'});
  $routeProvider.otherwise({redirectTo: '/welcome'}); // always the main section 
  
  $httpProvider.defaults.transformResponse.push(function(data, headers) {
    var userId = headers('Userid');
    if (userId) {
      console.debug(userId);
    }
    return data;
  });
}]);

// Todo: myApp.providers
/*var userProvider = function() {
  this.userService = {
    id: null
  };
  
  this.$get = function() {
    return userService;
  };
}*/
