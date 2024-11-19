const gulp = require('gulp'),
    clean = require('gulp-clean'),
    cssnano = require('gulp-cssnano'),
    sass = require('gulp-sass')(require('sass')),
    rename = require('gulp-rename'),
    webpack = require('webpack-stream');

gulp.task('watch', function () {
    gulp.watch(['src/scss/**/*.scss']).on(
        'change',
        gulp.series(
            'clean-shared',
            'clean-editor',
            'minify-shared',
            'minify-editor'
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

gulp.task('clean-editor', function () {
    return gulp.src('assets/css/editor.min.css', {
        read: false,
        allowEmpty: true,
    })
        .pipe(clean());
});

gulp.task('minify-shared', function () {
    return gulp.src('src/scss/main.scss')
        .pipe(sass({
            includePaths: ['./node_modules'],
        }).on('error', sass.logError))
        .pipe(cssnano({ zindex: false }))
        .pipe(gulp.dest('assets/css/'));
});

gulp.task('minify-editor', function () {
    return gulp.src('src/scss/editor.scss')
        .pipe(sass({
            includePaths: ['./node_modules'],
        }).on('error', sass.logError))
        .pipe(cssnano({ zindex: false }))
        .pipe(gulp.dest('assets/css/'));
});

gulp.task('js-bundling', function () {
    return gulp.src('src/js/main.js')
        .pipe(webpack({
            mode: "production",
            entry: {
                main: './src/js/main.js',
                "add-block-styles": './src/js/add-block-styles.js'
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
        'clean-editor',
        'minify-shared',
        'minify-editor',
        'js-bundling'
    )
);

