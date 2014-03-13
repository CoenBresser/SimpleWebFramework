'use strict';

/* Services */


// Demonstrate how to register services
angular.module('myApp.services', ['ngResource']).
//  value('version', '0.1').
/*  factory('Section', ['$resource', 
    function($resource){
      return $resource('data/v0.1/sections/:sectionId.json', {}, {
        query: {method:'GET', params:{sectionId:'all'}, isArray:true}
      });
    }]).
  factory('Article', ['$resource', 
    function($resource){
      return $resource('data/v0.1/articles/:sectionId-:articleId.json', {}, {
        query: {method:'GET', params:{articleId:'all'}, isArray:true}
      });
    }]).
  factory('Works', ['$resource', 
    function($resource){
      return $resource('data/v0.1/works/:sectionId-:galleryId.json', {}, {
        query: {method:'GET', params:{galleryId:'all'}, isArray:true}
      });
    }]); */
  factory('Section', ['$resource', 
    function($resource){
      return $resource('data/v1.0/sections/:sectionId', {}, {
        query: {method:'GET', params:{sectionId:''}, isArray:true}
      });
    }]).
  factory('Article', ['$resource', 
    function($resource){
      return $resource('data/v1.0/sections/:sectionId/articles/:articleId', {}, {
        query: {method:'GET', params:{articleId:''}, isArray:true}
      });
    }]).
  factory('Works', ['$resource', 
    function($resource){
      return $resource('data/v1.0/works/:workGroup', {}, {
        query: {method:'GET', isArray:true}
      });
    }]).
  factory('User', ['$resource', 
    function($resource){
      return $resource('data/v1.0/users/:userId', {}, {
        login: {method:'GET', params:{userId:'login'}, isArray:false},
        logout: {method:'GET', params:{userId:'logout'}, isArray:false}
      });
    }]);
