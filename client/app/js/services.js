'use strict';

/* Services */


// Demonstrate how to register services
angular.module('myApp.services', ['ngResource']).
//  value('version', '0.1').
  factory('Section', ['$resource', 
    function($resource){
      return $resource('data/v1.1/sections/:sectionId', {}, {
        query: {method:'GET', params:{sectionId:''}, isArray:true}
      });
    }]).
  factory('Article', ['$resource', 
    function($resource){
      return $resource('data/v1.1/sections/:sectionId/articles/:articleId', {}, {
        query: {method:'GET', params:{articleId:''}, isArray:true}
      });
    }]).
  factory('Works', ['$resource', 
    function($resource){
      return $resource('data/v1.1/works/:workGroup', {}, {
        query: {method:'GET', isArray:true}
      });
    }]).
  factory('User', ['$resource', 
    function($resource){
      return $resource('data/v1.1/users/:userId', {}, {
        login: {method:'GET', params:{userId:'login'}, isArray:false},
        logout: {method:'GET', params:{userId:'logout'}, isArray:false}
      });
    }]);
