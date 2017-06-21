# MyLittleGallery

A PHP class and templates to create a quick drop-in HTML gallery.

## Throubleshooting the demo script

### Unable to read files with non-ASCII names
#### PHP before 7.1
Check that encoding of `config.php` file itself matches value of GALLERY_FS_ENCODING constant.
#### PHP 7.1
`config.php` MUST be in `UTF-8`.
For PHP 7.1 GALLERY_FS_ENCODING and `$fsEncoding` parameter of the constructor are ignored.

Starting from PHP 7.1, [PHP uses internal_encoding to transcode file names](https://github.com/php/php-src/blob/e33ec61f9c1baa73bfe1b03b8c48a824ab2a867e/UPGRADING#L418).
Before that, file IO under Windows (notably) done using "default" (so-called "ANSI") character set (i.e. CP1251 for Russian cyrillic).
