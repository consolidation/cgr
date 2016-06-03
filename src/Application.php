<?php

namespace Consolidation\Cgr;

class Application
{
    /**
     * Run the cgr tool, a safer alternative to `composer global require`.
     *
     * @param array $argv The global $argv array passed in by PHP
     * @param string $home The path to the user's home directory
     * @return integer
     */
    public function run($argv, $home)
    {
        $commandList = $this->parseArgvAndGetCommandList($argv, $home);
        return $this->runCommandList($commandList);
    }

    /**
     * Figure out everything we're going to do, but don't do any of it
     * yet, just return the command objects to run.
     */
    public function parseArgvAndGetCommandList($argv, $home)
    {
        $optionDefaultValues = $this->getDefaultOptionValues($home);
        list($argv, $options) = $this->parseOutOurOptions($argv, $optionDefaultValues);
        list($projects, $composerArgs) = $this->separateProjectsFromArgs($argv, $options);

        $commandList = $this->getCommandStringList($composerArgs, $projects, $options);
        return $commandList;
    }

    /**
     * Run all of the commands in a list.  Abort early if any fail.
     *
     * @param array $commandList An array of CommandToExec
     * @return integer
     */
    public function runCommandList($commandList)
    {
        foreach ($commandList as $command) {
            $exitCode = $command->run();
            if ($exitCode) {
                return $exitCode;
            }
        }
        return 0;
    }

    /**
     * Return an array containing a list of commands to execute.  Depending on
     * the composition of the aguments and projects parameters, this list will
     * contain either a single command string to call through to composer (if
     * cgr is being used as a composer alias), or it will contain a list of
     * appropriate replacement 'composer global require' commands that install
     * each project in its own installation directory, while installing each
     * projects' binaries in the global Composer bin directory,
     * ~/.composer/vendor/bin.
     *
     * @param array $composerArgs
     * @param array $projects
     * @param array $options
     * @return CommandToExec
     */
    public function getCommandStringList($composerArgs, $projects, $options)
    {
        $command = $options['composer-path'];
        if (empty($projects)) {
            return array(new CommandToExec($command, $composerArgs));
        }
        return $this->globalRequire($command, $composerArgs, $projects, $options);
    }

    /**
     * Return our list of default option values, with paths relative to
     * the provided home directory.
     * @param string $home The user's home directory
     * @return array
     */
    public function getDefaultOptionValues($home)
    {
        return array(
            'composer-path' => 'composer',
            'base-dir' => "$home/.composer/global",
            'bin-dir' => "$home/.composer/vendor/bin",
        );
    }

    /**
     * We use our own special-purpose argv parser. The options that apply
     * to this tool are identified by a simple associative array, where
     * the key is the option name, and the value is its default value.
     * The result of this function is an array of two items containing:
     *  - An array of the items in $argv not used to set an option value
     *  - An array of options containing the user-specified or default values
     *
     * @param array $argv The global $argv passed in by php
     * @param array $optionDefaultValues An associative array
     * @return array
     */
    public function parseOutOurOptions($argv, $optionDefaultValues)
    {
        array_shift($argv);
        $passAlongArgvItems = array();
        $options = array();
        while (!empty($argv)) {
            $arg = array_shift($argv);
            if ((substr($arg, 0, 2) == '--') && array_key_exists(substr($arg, 2), $optionDefaultValues)) {
                $options[substr($arg, 2)] = array_shift($argv);
            } else {
                $passAlongArgvItems[] = $arg;
            }
        }
        return array($passAlongArgvItems, $options + $optionDefaultValues);
    }

    /**
     * After our options are removed by parseOutOurOptions, those items remaining
     * in $argv will be separated into a list of projects and versions, and
     * anything else that is not a project:version. Returns an array of two
     * items containing:
     *  - An associative array, where the key is the project name and the value
     *    is the version (or an empty string, if no version was specified)
     *  - The remaining $argv items not used to build the projects array.
     *
     * @param array $argv The $argv array from parseOutOurOptions()
     * @return array
     */
    public function separateProjectsFromArgs($argv)
    {
        $composerArgs = array();
        $projects = array();
        $sawGlobal = false;
        foreach ($argv as $arg) {
            // Any flags will just be passed through to each call to composer.
            if ($arg[0] == '-') {
                $composerArgs[] = $arg;
            } // Arguments containing a '/' name projects
            elseif (strpos($arg, '/') !== false) {
                $projectAndVersion = explode(':', strtr($arg, '=', ':'), 2) + array('', '');
                list($project, $version) = $projectAndVersion;
                $projects[$project] = $version;
            } // Arguments that are a Composer version should be attached
            // to the previous project.
            elseif ($this->isComposerVersion($arg)) {
                $keys = array_keys($projects);
                $lastProject = array_pop($keys);
                unset($projects[$lastProject]);
                $projects[$lastProject] = $arg;
            } elseif ($arg == 'global') {
                $sawGlobal = true;
            } else {
                if (($sawGlobal == false) || ($arg != 'require')) {
                    return array($argv, array());
                }
            }
        }
        return array($projects, $composerArgs);
    }

    /**
     * Provide a safer version of `composer global require`.  Each project
     * listed in $projects will be installed into its own project directory.
     * The binaries from each project will still be placed in the global
     * composer bin directory.
     *
     * @param string $command The path to composer
     * @param array $composerArgs Anything from the global $argv to be passed
     *   on to Composer
     * @param array $projects A list of projects to install, with the key
     *   specifying the project name, and the value specifying its version.
     * @param array $options User options from the command line; see
     *   $optionDefaultValues in the main() function.
     * @return integer
     */
    public function globalRequire($command, $composerArgs, $projects, $options)
    {
        $globalBaseDir = $options['base-dir'];
        $binDir = $options['bin-dir'];
        $env = array("COMPOSER_BIN_DIR" => $binDir);
        $result = array();
        foreach ($projects as $project => $version) {
            $installLocation = "$globalBaseDir/$project";
            $projectWithVersion = $this->projectWithVersion($project, $version);
            $commandToExec = $this->globalRequireOne($command, $composerArgs, $projectWithVersion, $env, $installLocation);
            $result[] = $commandToExec;
        }
        return $result;
    }

    /**
     * Create a separate installation directory for the project to be
     * installed into.  Creates the directory (and its parents), and
     * returns the path to the location created.
     *
     * @param string $project The project ("org/name") to be installed.
     * @param string $globalBaseDir The base location where all projects are
     *   installed (~/.composer/global)
     * @return string
     */
    public function createGlobalInstallLocation($project, $globalBaseDir)
    {
        $installLocation = "$globalBaseDir/$project";
        $this->mkdirParents($installLocation);
        return $installLocation;
    }

    /**
     * Return $project:$version, or just $project if there is no $version.
     *
     * @param string $project The project to install
     * @param string $version The version desired
     * @return string
     */
    public function projectWithVersion($project, $version)
    {
        if (empty($version)) {
            return $project;
        }
        return "$project:$version";
    }

    /**
     * Generate command string to call `composer require` to install one project.
     *
     * @param string $command The path to composer
     * @param array $composerArgs The arguments to pass to composer
     * @param string $projectWithVersion The project:version to install
     * @param array $env Environment to set prior to exec
     * @param string $installLocation Location to install the project
     * @return CommandToExec
     */
    public function globalRequireOne($command, $composerArgs, $projectWithVersion, $env, $installLocation)
    {
        $projectSpecificArgs = array("--working-dir=$installLocation", 'require', $projectWithVersion);
        $arguments = array_merge($composerArgs, $projectSpecificArgs);
        return new CommandToExec($command, $arguments, $env, $installLocation);
    }

    /**
     * Identify an argument that could be a Composer version string.
     *
     * @param string $arg The argument to test
     * @return boolean
     */
    public function isComposerVersion($arg)
    {
        $specialVersionChars = array('^', '~', '<', '>');
        return is_numeric($arg[0]) || in_array($arg[0], $specialVersionChars);
    }
}
