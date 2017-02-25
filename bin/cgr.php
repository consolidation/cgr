<?php

function main($argv)
{
    $appRoot = dirname(__DIR__);

    if (file_exists($appRoot.'/vendor/autoload.php')) {
        $autoload = include_once $appRoot.'/vendor/autoload.php';
    } elseif (file_exists($appRoot.'/../../autoload.php')) {
        $autoload = include_once $appRoot.'/../../autoload.php';
    } else {
        echo 'Could not find autoloader; try running `composer install`.'.PHP_EOL;
        exit(1);
    }

    // Find the home directory
    $home = \Consolidation\Cgr\SystemInformation::getHomeDir();

    $app = new \Consolidation\Cgr\Application();
    return $app->run($argv, $home);
}
