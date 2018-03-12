<?php

define('GALLERY_BASE_DIR', __DIR__);

/** Your filesystem encoding.
*
* UTF-8 is typical for modern *NIX,
* CP1251 is Russian Windows ANSI codepage.
*/
define('GALLERY_FS_ENCODING', 'UTF-8');
//define('GALLERY_FS_ENCODING', 'CP1251');

/** Enable files' descriptions support.
*
* Otherwise - use direct directory listing by mask.
* Files.bbs/Descript.ion is a popular file description list formats.
*/
//define('GALLERY_DESC_FILE', 'Files.bbs');
//define('GALLERY_DESC_FILE', 'Descript.ion');

/** Specify gallery descriptions encoding.
*
* CP866 for cyrillic descriptions is common.
* UTF-8 is a sane default suitable for drop-in gallery. (It's not used anyway then.)
*/
//define('GALLERY_DESC_ENCODING', 'CP866');
define('GALLERY_DESC_ENCODING', 'UTF-8');

/** Define general gallery layout
*/
define('GALLERY_PREVIEW_X', 160);
define('GALLERY_PREVIEW_Y', 120);
define('GALLERY_PREVIEW_WIDTH_FACTOR', 2);
define('GALLERY_COLUMNS', 3);

/** Allow X-SendFile/X-Accel-Redirect
*
* Uncomment one of the following defines, if you know what you are doing.
*
* NB: SERVER CONFIGURATION IS REQUIRED BEFORE ACTIVATING THIS FEATURE!
*/
// Lighttpd, Apache mod_xsendfile
//define('GALLERY_SENDFILE_HEADER', 'X-SendFile');
// nginx
//define('GALLERY_SENDFILE_HEADER', 'X-Accel-Redirect');
