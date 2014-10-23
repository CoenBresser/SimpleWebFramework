'use strict';

/* Directives */
angular.module('myApp.directives', []).
  directive('contenteditable', function() {
    return {
      restrict: 'A', // only activate on element attribute
      require: '?ngModel', // get a hold of NgModelController
      link: function(scope, element, attrs, ngModel) {
        if(!ngModel) return; // do nothing if no ng-model

        // Specify how UI should be updated
        ngModel.$render = function() {
          element.html(ngModel.$viewValue || '');
        };

        // Listen for change events to enable binding
        element.on('blur keyup change', function() {
          scope.$apply(read);
        });
        read(); // initialize

        // Write data to the model
        function read() {
          var html = element.html();
          // When we clear the content editable the browser leaves a <br> behind
          // If strip-br attribute is provided then we strip this out
          if( attrs.stripBr && html == '<br>' ) {
            html = '';
          }
          ngModel.$setViewValue(html);
        }
      }
    };
  })
  .directive('placeholder', function($timeout){
      // Fix the lacking placeholder in IE9
      var userAgent = navigator.userAgent;
      if (userAgent.indexOf('MSIE') < 0 || userAgent.indexOf('MSIE 1') > -1) {
          return {};
      }
      return {
          link: function(scope, elm, attrs){
              if (attrs.type === 'password') {
                  return;
              }
              $timeout(function(){
                  elm.val(attrs.placeholder).focus(function(){
                      if ($(this).val() == $(this).attr('placeholder')) {
                          $(this).val('');
                      }
                  }).blur(function(){
                      if ($(this).val() == '') {
                          $(this).val($(this).attr('placeholder'));
                      }
                  });
              });
          }
      }
  })
  .directive('snapDrag', function($parse){
    return {
      restrict: 'A',
      compile: function(element, attrs) {
        var fn = $parse(attrs['snapDrag']);
        return function(scope, element, attrs) { 
          element.on('drag', function(event) {
            if (!scope.followDrag) {
              // register
              scope.followDrag = function(eventStartX, eventStartY) {
                var startX = eventStartX;
                var startY = eventStartY;
                
                // Trigger an update at the start
                var currentDeltaX = null;
                var currentDeltaY = null;
                
                function snap(gridsize, value) {
                  return gridsize * Math.round(value/gridsize);
                }
                
                return {
                  update: function(x, y) {
                    // todo: figure out how to pass the grid size
                    var snapX = snap(150, x - startX);
                    var snapY = snap(150, y - startY);
                    if (snapX != currentDeltaX || snapY != currentDeltaY) {
                      event.deltaX = snapX;
                      event.deltaY = snapY;
                      
                      // Give delta from last update
                      event.tickX = snapX - currentDeltaX;
                      event.tickY = snapY - currentDeltaY;
                      
                      scope.$apply(function() {
                          fn(scope, {$event:event});
                      });
                      currentDeltaX = snapX;
                      currentDeltaY = snapY;
                    }
                  }
                };
              }(event.x, event.y);
            }
            if (event.x === 0 && event.y === 0) {
              // unregister
              delete scope.followDrag;
              return;
            }
            scope.followDrag.update(event.x, event.y);
          });
        };
      }
    };
  });
