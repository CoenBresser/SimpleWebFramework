'use strict';

angular.module('myApp.services', ['ngResource']).
  factory('Section', ['$resource', 
    function($resource){
      return $resource('data/v2.0/sections/:sectionId', {}, {
        query: {method:'GET', params:{sectionId:''}, isArray:true}
      });
    }]).
  factory('Article', ['$resource', 
    function($resource){
      return $resource('data/v2.0/articles/:articleId', {}, {
        query: {method:'GET', params:{articleId:''}, isArray:true}
      });
    }]).
  factory('Works', ['$resource', 
    function($resource){
      return $resource('data/v2.0/:workGroup/:workId', {}, {
        query: {method:'GET', isArray:true}
      });
    }]);
