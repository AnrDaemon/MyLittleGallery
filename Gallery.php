<?php
/** My Little Gallery
*
* A simple drop-in file-based HTML gallery.
*
* $Id: Gallery.php 662 2017-06-17 12:16:41Z anrdaemon $
*/

namespace AnrDaemon\MyLittleGallery;

use
  ArrayAccess,
  Countable,
  Exception,
  Imagick,
  Iterator,
  NumberFormatter,
  SplFileInfo;

class Gallery
implements ArrayAccess, Countable, Iterator
{
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

  // X-SendFile settings
  protected $sfPrefix;
  protected $sfHeader = 'X-SendFile';

  public static function fromListfile(SplFileInfo $target, $charset = 'CP866', $fsEncoding = null)
  {
    $path = $target->getRealPath();

    if(empty($path))
      throw new Exception('Can\'t use empty path.', 500);

    if(is_dir($path))
      throw new Exception('Target is a directory', 500);

    $self = new static($target->getPathInfo(), null, $fsEncoding);

    $f = iconv($charset, 'UTF-8', file_get_contents($path));

    if(preg_match_all('/^(\"?)(?P<name>[^\"]+?)\1\s+(?P<desc>.*?)\s*$/m', $f, $ta, PREG_SET_ORDER))
    {
      $prev = null;
      foreach($ta as $a)
      {
        $name = basename(trim($a['name']));
        $self->isSaneName($name);
        $self->params[$name]['desc'] = $a['desc'];
        $self->params[$name]['path'] = "{$self->path}/{$name}";
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

  public static function fromDirectory(SplFileInfo $target, $extensions = null, $fsEncoding = null)
  {
    $path = $target->getRealPath();

    if(empty($path))
      throw new Exception('Can\'t use empty path.', 500);

    if(!is_dir($path))
      throw new Exception('Target is not a directory', 500);

    if(!is_array($extensions))
      $extensions = null;

    $self = new static($target, $extensions, $fsEncoding);

    $mask = iconv('UTF-8', $self->cs, "*.{" . implode(',', $self->extensions) . "}");

    $prev = null;
    foreach(glob("{$path}/{$mask}", GLOB_BRACE | GLOB_MARK) as $fname)
    {
      if(is_dir($fname))
        continue;

      $name = iconv($self->cs, 'UTF-8', basename($fname));
      $self->isSaneName($name);
      $self->params[$name]['desc'] = $name;
      $self->params[$name]['path'] = "{$self->path}/{$name}";
      if(isset($prev))
      {
        $self->params[$name]['prev'] = $prev;
        $self->params[$prev]['next'] = $name;
      }
      $prev = $name;
    }

    return $self;
  }

  public function showIndex($template = null)
  {
    if(empty($template))
    {
      $template = '<div><a href="%1$s" target="_blank"><img src="%2$s" alt="%3$s"/></a><p><a href="%1$s" target="_blank">%3$s</a></p></div>';
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

  public function allowSendFile($prefix, $header = null)
  {
    $this->sfPrefix = $prefix;
    $this->sfHeader = trim($header)?: 'X-SendFile';
  }

  public function sendFile($path)
  {
    if(empty($this->sfPrefix))
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

    header("{$this->sfHeader}: {$this->sfPrefix}$path");

    return true;
  }

  public function imageFileSize($name, $divisor = 1)
  {
    return $this->nf->format(ceil(filesize(iconv('UTF-8', $this->cs, "{$this->path}/$name")) / $divisor));
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

  public function getPrefix($name)
  {
    return $this->prefix[$name];
  }

  public function getPath()
  {
    return $this->path;
  }

  public function thumbnailImage($name)
  {
    $path = iconv('UTF-8', $this->cs, "{$this->path}/.preview/$name");
    if(is_file($path))
      return true;

    try
    {
      $img = new Imagick(iconv('UTF-8', $this->cs, "{$this->path}/$name"));
      $img->thumbnailImage($this->pWidth, $this->pHeight, true);
      $img->writeImage($path);
    }
    catch(Exception $e)
    {
      if(!is_dir(dirname($path)))
        mkdir(dirname($path));
      return false;
    }

    return true;
  }

  // FIX anti-exploit
  public function isSaneName($fname)
  {
    $name = basename($fname);
    if(preg_match('/[^!#$%&\'()+,\-.;=@\[\]^_`{}~\p{L}\d\s]/uiS', $name))
      throw new Exception('Invalid character in name \'' . $name . "'.", 403);

    if(!preg_match('{.+\.(' . implode('|', array_map('preg_quote', $this->extensions)) . ')$}u', $name))
      throw new Exception('Invalid filename extension.', 403);

    return true;
  }

// Magic!

  protected function __construct(SplFileInfo $path, $extensions = null, $fsEncoding = null)
  {
    $this->cs = trim($fsEncoding) ?: 'UTF-8';
    $this->path = iconv($this->cs, 'UTF-8', $path->getRealPath());

    // $path is not necessarily equals $path->getRealPath()
    // Work off original $path
    $this->prefix['index'] = iconv($this->cs, 'UTF-8', substr($path, strlen($_SERVER['DOCUMENT_ROOT'])));
    $this->prefix['view'] = $this->prefix['index'] . '/?show=';
    $this->prefix['thumbnail'] = $this->prefix['index'] . '/?preview=';
    $this->prefix['image'] = $this->prefix['index'] . '/?view=';

    $this->setNumberFormatter();
    $this->setPreviewSize();

    if(!is_array($extensions))
    {
      $this->extensions = array('gif', 'jpg', 'png');
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
