const gulp = require('gulp'),
    clean = require('gulp-clean'),
    concatCss = require('gulp-concat-css'),
    cssnano = require('gulp-cssnano'),
    sass = require('gulp-sass')(require('sass')),
    rename = require('gulp-rename'),
    webpack = require('webpack-stream');

gulp.task('watch', function () {
    gulp.watch(['src/scss/**/*.scss']).on(
        'change',
        gulp.series(
            'clean-shared',
            // 'clean-editor-only',
            // 'clean-blocks',
            // 'clean-login',
            'minify-shared',
            // 'minify-editor-only',
            // 'minify-blocks',
            // 'minify-login',
        )
    );
});

gulp.task('clean-shared', function () {
    return gulp.src('assets/css/style-shared.min.css', {
        read: false,
        allowEmpty: true,
    })
        .pipe(clean());
});

// gulp.task('clean-editor-only', function () {
//     return gulp.src('assets/css/editor-only.min.css', {
//         read: false,
//         allowEmpty: true,
//     })
//         .pipe(clean());
// });

// gulp.task('clean-blocks', function () {
//     return gulp.src('assets/css/blocks/*.min.css', {
//         read: false,
//         allowEmpty: true,
//     })
//         .pipe(clean());
// });

// gulp.task('clean-login', function () {
//     return gulp.src('assets/css/login/caes-login.min.css', {
//         read: false,
//         allowEmpty: true,
//     })
//         .pipe(clean());
// });

gulp.task('minify-shared', function () {
    return gulp.src('src/scss/*.scss')
        .pipe(sass({
            includePaths: ['./node_modules'],
        }).on('error', sass.logError))
        .pipe(concatCss('main.min.css'))
        .pipe(cssnano())
        .pipe(gulp.dest('assets/css/'));
});

// gulp.task('minify-editor-only', function () {
//     return gulp.src('src/scss/editor-only/*.scss')
//         .pipe(sass({
//             includePaths: ['./node_modules'],
//         }).on('error', sass.logError))
//         .pipe(concatCss('editor-only.min.css'))
//         .pipe(cssnano())
//         .pipe(gulp.dest('assets/css/editor-only'));
// });

// gulp.task('minify-blocks', function () {
//     return gulp.src('src/scss/blocks/*.scss')
//         .pipe(sass().on('error', sass.logError))
//         .pipe(rename({ suffix: '.min' }))
//         .pipe(cssnano())
//         .pipe(gulp.dest('assets/css/blocks'));
// });

// gulp.task('minify-login', function () {
//     return gulp.src('src/scss/login/*.scss')
//         .pipe(sass({
//             includePaths: ['./node_modules'],
//         }).on('error', sass.logError))
//         .pipe(concatCss('caes-login.min.css'))
//         .pipe(cssnano())
//         .pipe(gulp.dest('assets/css/login'));
// });

gulp.task('js-bundling', function () {
    return gulp.src('src/js/main.js')
        .pipe(webpack({
            mode: "production",
            entry: {
                main: './src/js/main.js',
                // "remove-block-styles": './src/js/remove-block-styles.js',
                // "add-block-styles": './src/js/add-block-styles.js'
            },
            output: {
                filename: '[name].js',
            },
        }))
        .pipe(gulp.dest('assets/js'));
});

gulp.task(
    'default',
    gulp.series(
        'clean-shared',
        // 'clean-editor-only',
        // 'clean-blocks',
        // 'clean-login',
        'minify-shared',
        // 'minify-editor-only',
        // 'minify-blocks',
        // 'minify-login',
        'js-bundling'
    )
);

