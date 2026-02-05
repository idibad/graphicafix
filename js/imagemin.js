const imagemin = require('imagemin');
const imageminMozjpeg = require('imagemin-mozjpeg');
const imageminPngquant = require('imagemin-pngquant');
const imageminGifsicle = require('imagemin-gifsicle');
const imageminSvgo = require('imagemin-svgo');
const path = require('path');

const inputFolder = 'images'; // Input folder containing images
const outputFolder = 'compressed_images'; // Output folder for compressed images

(async () => {
    const files = await imagemin([path.join(inputFolder, '*.{jpg,png,gif,svg}')], {
        destination: outputFolder,
        plugins: [
            imageminMozjpeg({ quality: 75 }),
            imageminPngquant({ quality: [0.6, 0.8] }),
            imageminGifsicle(),
            imageminSvgo()
        ]
    });

    console.log('Images optimized:', files);
})();
