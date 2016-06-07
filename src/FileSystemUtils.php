<?php

namespace Consolidation\Cgr;

/**
 * A few convenience utility functions for filesystem operations.
 */
class FileSystemUtils
{
    /**
     * Set the current working directory if it was specified.
     */
    public static function applyDir($dir)
    {
        if (empty($dir)) {
            return $dir;
        }
        $origDir = getcwd();
        static::mkdirParents($dir);
        chdir($dir);
        return $origDir;
    }

    /**
     * Create a directory at the specified path. Also create any parent
     * directories that do not yet exist.
     *
     * @param $path The directory path to create.
     * @return boolean
     */
    public static function mkdirParents($path)
    {
        if (is_dir($path)) {
            return true;
        }

        if (static::mkdirParents(dirname($path))) {
            return mkdir($path);
        }
    }
}
