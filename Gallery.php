<?php
/** My Little Gallery
*
* A simple drop-in file-based HTML gallery.
*
* $Id: Gallery.php 674 2017-06-24 23:01:04Z anrdaemon $
*/

namespace AnrDaemon\MyLittleGallery;

use
  ArrayAccess,
  Countable,
  Exception,
  ErrorException,
  Imagick,
  Iterator,
  NumberFormatter,
  SplFileInfo;

class Gallery
implements ArrayAccess, Countable, Iterator
{
  const previewTemplate =
    '<div><a href="%1$s" target="_blank"><img src="%2$s" alt="%3$s"/></a><p><a href="%1$s" target="_blank">%3$s</a></p></div>';

  const defaultTypes = 'gif|jpeg|jpg|png|wbmp|webp';

  // All paths are UTF-8! (Except those from SplFileInfo)
  protected $path; // Gallery base path
  protected $prefix = array(); // Various prefixes for correct links construction
  protected $params = array(); // Files list
  protected $extensions = array(); // Allowed extensions

  // FS encoding
  protected $cs;

  // Numbers formatter
  protected $nf;

  // Preview settings
  protected $pWidth;
  protected $pHeight;
  protected $template;

  // X-SendFile settings
  protected $sfPrefix;
  protected $sfHeader = 'X-SendFile';

  protected function fromFileList(array $list)
  {
    $prev = null;
    foreach($list as $fname)
    {
      if(is_dir($fname))
        continue;

      $name = iconv($this->cs, 'UTF-8', basename($fname));
      $this->isSaneName($name);

      $meta = getimagesize($fname);
      if($meta === false || $meta[0] === 0 || $meta[1] === 0)
        continue;

      $this->params[$name] = new ArrayIterator([
        'desc' => $name,
        'path' => "{$this->path}/{$name}",
        'width' => $meta[0],
        'height' => $meta[1],
        'mime' => $meta['mime'],
      ]);
      if(isset($prev))
      {
        $this->params[$name]['prev'] = $prev;
        $this->params[$prev]['next'] = $name;
      }
      $prev = $name;
    }

    return $this;
  }

  public static function fromListfile(SplFileInfo $target, $charset = 'CP866', $fsEncoding = null)
  {
    $path = $target->getRealPath();

    if(empty($path))
      throw new Exception('Can\'t use empty path.', 500);

    if(is_dir($path))
      throw new Exception('Target is a directory', 500);

    $self = new static($target->getPathInfo(), null, $fsEncoding);

    $f = iconv($charset, 'UTF-8', file_get_contents($path));

    if(preg_match_all('/^(\"?)(?P<name>[^\"]+?)\1\s+(?P<desc>.*?)\s*$/um', $f, $ta, PREG_SET_ORDER))
    {
      $prev = null;
      foreach($ta as $a)
      {
        $name = basename(trim($a['name']));
        $self->isSaneName($name);

        $meta = getimagesize(iconv('UTF-8', $self->cs, "{$self->path}/$name"));
        if($meta === false || $meta[0] === 0 || $meta[1] === 0)
          continue;

        $this->params[$name] = new ArrayIterator([
          'desc' => $a['desc'],
          'path' => "{$self->path}/{$name}",
          'width' => $meta[0],
          'height' => $meta[1],
          'mime' => $meta['mime'],
        ]);
        if(isset($prev))
        {
          $self->params[$name]['prev'] = $prev;
          $self->params[$prev]['next'] = $name;
        }
        $prev = $name;
      }
    }

    return $self;
  }

  public static function fromDirectory(SplFileInfo $target, array $extensions = null, $fsEncoding = null)
  {
    if(empty($extensions))
    {
      $extensions = explode('|', static::defaultTypes);
    }

    $mask = "*.{" . implode(',', $extensions) . "}";

    return static::fromCustomMask($target, $mask, $fsEncoding);
  }

  public static function fromCustomMask(SplFileInfo $target, $mask, $fsEncoding = null)
  {
    $path = $target->getRealPath();

    if(empty($path))
      throw new Exception('Can\'t use empty path.', 500);

    if(!is_dir($path))
      throw new Exception('Target is not a directory', 500);

    $self = new static($target, null, $fsEncoding);

    return $self->fromFileList(glob("{$path}/" . iconv('UTF-8', $self->cs, $mask), GLOB_BRACE | GLOB_MARK));
  }
  /**
  * $template($show, $preview, $description)
  */
  public function showIndex($template = null)
  {
    if(empty($template))
    {
      $template = $this->template;
    }

    $gp = '';
    foreach($this->params as $f => $d)
    {
      $gp .= sprintf($template, htmlspecialchars($this->prefix['view'] . rawurlencode($f)),
        htmlspecialchars($this->prefix['thumbnail'] . rawurlencode($f)),
        htmlspecialchars($d['desc'] . ' (' . $this->imageFileSize($f, 1024) . "\xC2\xA0kB)"));
    }

    return $gp;
  }

  public function setNumberFormatter($locale = 'en_US.UTF-8', $style = NumberFormatter::DECIMAL, $pattern = '')
  {
    $this->nf = new NumberFormatter($locale, $style, $pattern);

    return $this;
  }

  public function allowSendFile($prefix = null, $header = null)
  {
    $this->sfPrefix = $prefix;
    $this->sfHeader = trim($header)?: 'X-SendFile';

    return $this;
  }

  public function sendFile($path)
  {
    if(!isset($this->sfPrefix))
      return false;

    header_register_callback(function(){
      /*
      Accept-Ranges
      Cache-Control
      Content-Disposition
      Content-Type
      Expires
      Set-Cookie
      */
      header_remove('Accept-Ranges');
      header_remove('Content-Type');
    });

    header("{$this->sfHeader}: {$this->sfPrefix}" . urlencode("$path"));

    return true;
  }

  public function imageFileSize($name, $divisor = 1)
  {
    return $this->nf->format(ceil(filesize(iconv('UTF-8', $this->cs, "{$this->path}/$name")) / $divisor));
  }

  public function imagePreviewExists($name)
  {
    if(isset($this->params[$name]['preview']))
      return !empty($this->params[$name]['preview']);

    $fname = iconv('UTF-8', $this->cs, "{$this->path}/.preview/$name");
    return $this->params[$name]['preview'] = file_exists($fname);
  }

  public function setPreviewSize($width = null, $height = null)
  {
    if((int)$width < 0 || (int)$height < 0)
      throw new Exception('Thumbnail dimensions can\'t be negative.', 500);

    $this->pWidth = (int)$width ?: 160;
    $this->pHeight = (int)$height ?: 120;

    return $this;
  }

  public function setPrefix($name, $prefix)
  {
    if(!isset($this->prefix[$name]))
      throw new Exception("Unknown prefix '$name'.", 500);

    $this->prefix[$name] = $prefix;

    return $this;
  }

  public function setTemplate($template = null)
  {
    $this->template = empty($template)
      ? static::previewTemplate
      : $template;

    return $this;
  }

  public function getPrefix($name)
  {
    return $this->prefix[$name];
  }

  public function getPath($name = null, $local = null)
  {
    $path = $this->path;
    if(isset($name))
    {
      $path .= $name;
    }

    if($local)
    {
      $path = iconv('UTF-8', $this->cs, $path);
    }

    return $path;
  }

  public function getUrl($prefix, $name)
  {
    return $this->prefix[$prefix] . ($prefix === 'index' ? '/' : rawurlencode($name));
  }

  public function thumbnailImage($name)
  {
    static $gdSave = array(
      'image/gif' => 'imagegif',
      'image/jpeg' => 'imagejpeg',
      'image/png' => 'imagepng',
      'image/vnd.wap.wbmp' => 'imagewbmp',
      'image/webp' => 'imagewebp',
    );

    if(!isset($this->params[$name]))
      throw new Exception("Image '$name' is not registered in the gallery.", 404);

    $path = $this->getPath("/.preview/$name", true);
    if(is_file($path))
      return true;

    try
    {
      set_error_handler(function($s, $m, $f, $l, $c = null) { throw new ErrorException($m, 0, $s, $f, $l); });

      if(class_exists('Imagick'))
      {
        $img = new Imagick("{$this->path}/$name");
        $img->thumbnailImage($this->pWidth, $this->pHeight, true);
        $img->writeImage("{$this->path}/.preview/$name");
      }
      elseif(function_exists('imagecreatefromstring'))
      {
        $src = imagecreatefromstring(file_get_contents($this->getPath("/$name", true)));
        if($src === false)
          throw new Exception("The file '$name' can't be interpreted as image.", 500);

        $oFactor = $this->params[$name]['width'] / $this->params[$name]['height'];
        $tFactor = $this->pWidth / $this->pHeight;
        if($oFactor >= $tFactor)
        {
          $w = $this->pWidth;
          $h = min($this->pHeight, ceil($this->pWidth / $oFactor));
        }
        else
        {
          $w = min($this->pWidth, ceil($this->pHeight * $oFactor));
          $h = $this->pHeight;
        }

        $img = imagecreatetruecolor($w, $h);
        if(!imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, $this->params[$name]['width'], $this->params[$name]['height']))
          throw new Exception("Unable to create thumbnail for '$name'.", 500);

        $gdSave[$this->params[$name]['mime']]($img, $path);
      }
      else
        throw new ErrorException('Imagick or gd2 extension is required to create thumbnails at runtime.', 501);

      restore_error_handler();
    }
    catch(Exception $e)
    {
      restore_error_handler();
      if(!is_dir(dirname($path)))
      {
        mkdir(dirname($path));
        return false;
      }

      throw $e;
    }

    return true;
  }

  // FIX anti-exploit
  public function isSaneName($fname)
  {
    $name = basename($fname);
    if(preg_match('/[^!#$%&\'()+,\-.;=@\[\]^_`{}~\p{L}\d\s]/uiS', $name))
      throw new Exception("Invalid character in name '$name'.", 400);

    if(!preg_match('{.+\.(' . implode('|', array_map('preg_quote', $this->extensions)) . ')$}ui', $name))
      throw new Exception('Invalid filename extension.', 400);

    return true;
  }

// Magic!

  protected function __construct(SplFileInfo $path, array $extensions = null, $fsEncoding = null)
  {
    if(version_compare(PHP_VERSION, '7.1', '<'))
    {
      $this->cs = trim($fsEncoding) ?: 'UTF-8';
    }
    else
    {
      ini_set('internal_encoding', 'UTF-8');
      $this->cs = 'UTF-8';
    }

    $this->path = iconv($this->cs, 'UTF-8', realpath($path->getRealPath()));

    // $path is not necessarily equals $path->getRealPath()
    // Work off original $path
    $this->prefix['index'] = iconv($this->cs, 'UTF-8', substr(realpath(realpath($path)), strlen(realpath(realpath($_SERVER['DOCUMENT_ROOT'])))));
    $this->prefix['view'] = $this->prefix['index'] . '/?show=';
    $this->prefix['thumbnail'] = $this->prefix['index'] . '/?preview=';
    $this->prefix['image'] = $this->prefix['index'] . '/?view=';

    $this->setNumberFormatter();
    $this->setPreviewSize();
    $this->setTemplate();

    if(empty($extensions))
    {
      $this->extensions = explode('|', static::defaultTypes);
    }
    else
    {
      $masks = array();
      foreach(array_map('trim', $extensions) as $ext)
      {
        if(empty($ext))
          continue;

        $masks[] = $ext;
      }

      if(empty($masks))
        throw new Exception('File extensions can\'t be empty strings.', 500);

      $this->extensions = $masks;
    }
  }

// ArrayAccess

  public function offsetSet($offset, $value)
  {
    $this->params[$offset] = $value;
  }

  public function offsetGet($offset)
  {
    return $this->params[$offset];
  }

  public function offsetExists($offset)
  {
    return isset($this->params[$offset]);
  }

  public function offsetUnset($offset)
  {
    unset($this->params[$offset]);
  }

// Countable

  public function count()
  {
    return count($this->params);
  }

// Iterator

  public function current()
  {
    return current($this->params);
  }

  public function key()
  {
    return key($this->params);
  }

  public function next()
  {
    return next($this->params);
  }

  public function rewind()
  {
    return reset($this->params);
  }

  public function valid()
  {
    return key($this->params) !== null;
  }
}
