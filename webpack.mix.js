let mix = require("laravel-mix").mix;

mix.setPublicPath(__dirname);

mix
    .sass("assets/vendor/debugbar/scss/debugbar.scss", "assets/debugbar.css")
    .sass("assets/vendor/debugbar/scss/openhandler.scss", "assets/openhandler.css")
    .sass("assets/vendor/debugbar/scss/widgets.scss", "assets/widgets.css")
    .combine(["assets/vendor/debugbar/js/debugbar.js","assets/vendor/debugbar/js/openhandler.js", "assets/vendor/debugbar/js/widgets.js"], "assets/debugbar.js");
