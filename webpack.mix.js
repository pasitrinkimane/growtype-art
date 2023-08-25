let mix = require('laravel-mix');

mix.setPublicPath('./public');
mix.setResourceRoot('./../');

mix
    .sass('resources/styles/growtype-ai.scss', 'styles');

mix
    .js('resources/scripts/growtype-ai.js', 'scripts');

mix
    .copyDirectory('resources/images', 'public/images');

mix
    .sourceMaps()
    .version();
