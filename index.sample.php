<?php

if(is_file(__DIR__ . '/Gallery.php'))
{
  require_once __DIR__ . '/Gallery.php';
}

if(!defined('GALLERY_BASE_DIR'))
{
  require_once __DIR__ . '/config.php';
}

ini_set('default_charset', 'UTF-8');
setlocale(LC_ALL, 'C.' . GALLERY_FS_ENCODING);

if('cli' === PHP_SAPI)
{
  $_REQUEST['console'] = true;

  if($argc > 1)
  {
    $_REQUEST['preview'] = $argv[1];
  }
  else
  {
    die("Not enough arguments!\n");
  }
}

set_exception_handler(function($e)
{
  static $map = array(
    400 => 'Bad request',
    403 => 'Forbidden',
    404 => 'Not found',
    405 => 'Method not allowed',
    415 => 'Unsupported media type',
    500 => 'Internal server error',
    501 => 'Not implemented',
  );
  $code = isset($map[$e->getCode()]) ? $e->getCode() : 500;
  http_response_code($code);
  exit("<!DOCTYPE html>
<html><head>
<title>{$code} {$map[$code]}</title>
</head><body><h1>{$map[$code]}</h1>
<p>" . htmlspecialchars($e->getMessage()) . "</p>
<hr/>
{$_SERVER['SERVER_SIGNATURE']}</body></html>");
});

if(defined('GALLERY_DESC_FILE'))
{
  $gallery = AnrDaemon\MyLittleGallery\Gallery::fromListfile(new SplFileInfo(GALLERY_BASE_DIR . "/" . GALLERY_DESC_FILE),
    GALLERY_DESC_ENCODING, GALLERY_FS_ENCODING);
}
else
{
  $gallery = AnrDaemon\MyLittleGallery\Gallery::fromDirectory(new SplFileInfo(GALLERY_BASE_DIR),
    null, GALLERY_FS_ENCODING);
}

if(defined('GALLERY_SENDFILE_HEADER'))
{
  $gallery->allowSendFile($gallery->getPrefix('index'), GALLERY_SENDFILE_HEADER);
}

$gallery->setPreviewSize(GALLERY_PREVIEW_X, GALLERY_PREVIEW_Y);

switch(true)
{
  case isset($_REQUEST['preview']):
    $name = basename($_REQUEST['preview']);
    if(!isset($gallery[$name]))
      throw new Exception('No referenced image found.', 404);

    if(!$gallery->thumbnailImage($name))
      throw new Exception("No thumbnail image for '$name'.", 404);

    if(isset($_REQUEST['console']))
    {
      die("Done.\n");
    }

    if($gallery->sendFile("/.preview/$name"))
      break;

    header('Content-type: ' . $gallery[$name]['mime']);
    readfile($gallery->getPath("/.preview/$name", true));

    break;
  case isset($_REQUEST['view']):
    $name = basename($_REQUEST['view']);
    if(!isset($gallery[$name]))
      throw new Exception("Image '$name' not found.", 404);

    if($gallery->sendFile("/$name"))
      break;

    header('Content-type: ' . $gallery[$name]['mime']);
    readfile($gallery->getPath("/$name", true));

    break;
  case isset($_REQUEST['show']):
    $name = basename($_REQUEST['show']);
?><?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html>
<html>
<head>
<title><?=htmlspecialchars($gallery[$name]['desc'])?> - My Little Gallery</title>
<link rel="INDEX UP" href="<?=htmlspecialchars($gallery->getUrl('index'))?>"/>
<?php if(isset($gallery[$name]['prev'])):?>
<link rel="PREVIOUS" href="<?=htmlspecialchars($gallery->getUrl('view', $gallery[$name]['prev']))?>"/>
<?php endif;
if(isset($gallery[$name]['next'])):?>
<link rel="NEXT" href="<?=htmlspecialchars($gallery->getUrl('view', $gallery[$name]['next']))?>"/>
<?php endif; ?>
<style type="text/css"><!--
h1 {
  padding-top: 1ex;
}

h1, p {
  text-align: center;
}

img {
  max-width: 100%;
  margin: 5px 0px;
  cursor: pointer;
}

div.navFloater {
  position: absolute;
  top: 0px;
  padding: 1em;
}

div.prev {
  left: 0px;
}

div.next {
  right: 0px;
}
//--></style>
</head>
<body>
<h1><?=htmlspecialchars($gallery[$name]['desc'])?></h1>
<p><img src="<?=htmlspecialchars($gallery->getUrl('image', $name))?>
" alt="<?=htmlspecialchars($gallery[$name]['desc'] . ' (' . $gallery->imageFileSize($name, 1024) . "\xC2\xA0kB)")?>
" title="" onclick="window.close();"/></p>
<?php if(isset($gallery[$name]['prev'])):?>
<div class="navFloater prev"><a href="<?=htmlspecialchars($gallery->getUrl('view', $gallery[$name]['prev']))?>
" title="<?=htmlspecialchars($gallery[$gallery[$name]['prev']]['desc'])?>
"><?=htmlspecialchars("<< {$gallery[$gallery[$name]['prev']]['desc']}")?></a></div>
<?php endif;
if(isset($gallery[$name]['next'])):?>
<div class="navFloater next"><a href="<?=htmlspecialchars($gallery->getUrl('view', $gallery[$name]['next']))?>
" title="<?=htmlspecialchars($gallery[$gallery[$name]['next']]['desc'])?>
"><?=htmlspecialchars("{$gallery[$gallery[$name]['next']]['desc']} >>")?></a></div>
<?php endif; ?>
<!-- Forum embed code
[url=<?="{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}" . htmlspecialchars($gallery->getUrl('view', $name))?>
][img]<?="{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}" . htmlspecialchars($gallery->getUrl('thumbnail', $name))?>[/img][/url]
[url=<?="{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}" . htmlspecialchars($gallery->getUrl('view', $name))?>
]<?=htmlspecialchars($gallery[$name]['desc'] . ' (' . $gallery->imageFileSize($name, 1024) . "\xC2\xA0kB)")?>[/url]
-->
</body>
</html>
<?php
    break;
  default:
?><?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html>
<html>
<head>
<title>My Little Gallery</title>
<style type="text/css"><!--
body {
  max-width: <?=(GALLERY_PREVIEW_X * GALLERY_PREVIEW_WIDTH_FACTOR * GALLERY_COLUMNS)?>px;
  text-align: center;
  margin: 0px auto;
}
div.MyLittleGallery {
  position: relative;
  text-align: left;
}
div.MyLittleGallery div {
  position: relative;
  width: <?=(GALLERY_PREVIEW_X * GALLERY_PREVIEW_WIDTH_FACTOR)?>px;
  margin: 1em 0px;
  display: inline-block;
  text-align: center;
}
div.MyLittleGallery img {
  margin: 1em 0px 0px;
}

//--></style>
</head>
<body><p><a href="./..">Go up</a></p>
<div class="MyLittleGallery"><?=$gallery->showIndex()?></div>
<!-- pre style="text-align: left;"><?php//=print_r($gallery, true)?></pre -->
</body></html>
<?php
}
