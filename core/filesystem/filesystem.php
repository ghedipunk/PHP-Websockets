<?php
namespace Phpws;


class Filesystem
{
  /**
   * Gets the actual file name, or false if the file doesn't exist
   *
   * Searches through the current path or, if preceeded with a /, from the root
   * directory for a given file name and returns the actual name of the file.
   * If $caseSensitive is true (by default), it will not attempt to find the
   * file name if it does not immediately match.
   *
   * @param string $fileName The file name being searched for.
   * @param bool $caseSensitive
   */
  public function fileExists($fileName, $caseSensitive = true)
  {
    if (file_exists($fileName) && (is_file($fileName) || is_link($fileName))
    {
      return $fileName;
    }
    
    if ($caseSensitive)
    {
      return false;
    }

    $parentName = dirname($fileName);
    $fileArray = glob($parentName . '/*', GLOB_NOSORT);
  }

  public function directoryExists($dirName, $caseSensitive = true)
  {
    if (file_exists($dirName) && is_dir($dirName))
    {
      return $dirName;
    }

    if ($caseSensitive)
    {
      return false;
    }

    $parentName = dirname($fileName);
    $fileArray = glob($parentName . '/*', GLOB_NOSORT);
    
  }
}