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

    /**
     * Return all of the directories in a given directory
     *
     * @param string $d directory to scann
     * @return string[]
     */
    public static function listDirectories($d)
    {
        return array_filter(scandir($d), function ($f) use ($d) {
            return is_dir($d . DIRECTORY_SEPARATOR . $f) && ($f[0] != '.');
        });
    }


    /**
     * Find all installed projects in the global 'base-dir'.
     *
     * @param string $globalBaseDir
     * @return string[]
     */
    public static function allInstalledProjectsInBaseDir($globalBaseDir)
    {
        $projects = array();

        $orgs = static::listDirectories($globalBaseDir);
        foreach ($orgs as $org) {
            $projects = array_merge($projects, static::allInstalledProjectsInOneOrg($globalBaseDir, $org));
        }

        return $projects;
    }

    /**
     * Find all installed projects in one organization dir.
     *
     * @param string $globalOrgDir
     * @return string[]
     */
    protected static function allInstalledProjectsInOneOrg($globalBaseDir, $org)
    {
        $globalOrgDir = "$globalBaseDir/$org";
        $projects = array();

        $projectDirs = static::listDirectories($globalOrgDir);
        foreach ($projectDirs as $projectDir) {
            if (is_file("$globalOrgDir/$projectDir/composer.json")) {
                $projects[] = "$org/$projectDir";
            }
        }

        return $projects;
    }
}
