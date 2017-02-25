<?php

namespace Consolidation\Cgr;

/**
 * Determine information about the system
 */
class SystemInformation
{
    /**
     * @throws \RuntimeException
     * @return string
     */
    public static function getHomeDir()
    {
        $home = getenv('COMPOSER_HOME');
        if ($home) {
            return $home;
        }
        if (self::isWindows()) {
            if (!getenv('APPDATA')) {
                throw new \RuntimeException('The APPDATA or COMPOSER_HOME environment variable must be set for composer to run correctly');
            }
            return rtrim(strtr(getenv('APPDATA'), '\\', '/'), '/') . '/Composer';
        }
        $userDir = self::getUserDir();
        if (is_dir($userDir . '/.composer')) {
            return $userDir . '/.composer';
        }
        if (self::useXdg()) {
            // XDG Base Directory Specifications
            $xdgConfig = getenv('XDG_CONFIG_HOME') ?: $userDir . '/.config';
            return $xdgConfig . '/composer';
        }
        return $userDir . '/.composer';
    }

    /**
     * @return bool Whether the host machine is running a Windows OS
     */
    public static function isWindows()
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * @return bool
     */
    private static function useXdg()
    {
        foreach (array_keys($_SERVER) as $key) {
            if (substr($key, 0, 4) === 'XDG_') {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws \RuntimeException
     * @return string
     */
    private static function getUserDir()
    {
        $home = getenv('HOME');
        if (!$home) {
            throw new \RuntimeException('The HOME or COMPOSER_HOME environment variable must be set for composer to run correctly');
        }
        return rtrim(strtr($home, '\\', '/'), '/');
    }
}
