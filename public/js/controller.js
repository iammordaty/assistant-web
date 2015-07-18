// https://github.com/xiaohuame/TankWar/blob/master/js/controller.js#L9
// http://verekia.com/requirejs/build-simple-client-side-mvc-app-require-js

var Controller = (function () {
    'use strict';

    return function (config) {
        this.config = config;

        this.init = function () {
            console.log(this.config);

            this.go();
        };

        this.go = function () {
            console.log('go');
        };
    };
}());

var controller = new Controller({ x: 1, y: 2 });

controller.init();

