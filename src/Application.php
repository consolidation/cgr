<?php

namespace Consolidation\Cgr;

/**
 * Note that this command is deliberately written using only php-native
 * libraries, and no external dependencies whatsoever, so that it may
 * be installed via `composer global require` without causing any conflicts
 * with any other project.
 *
 * This technique is NOT recommended for other tools. Use Symfony Console
 * directly, or, better yet, use Robo (http://robo.li) as a framework.
 * See: http://robo.li/framework/
 */
class Application
{
    protected $outputFile = '';

    /**
     * Run the cgr tool, a safer alternative to `composer global require`.
     *
     * @param array $argv The global $argv array passed in by PHP
     * @param string $home The path to the composer home directory
     * @return integer
     */
    public function run($argv, $home)
    {
        $optionDefaultValues = $this->getDefaultOptionValues($home);
        $optionDefaultValues = $this->overlayEnvironmentValues($optionDefaultValues);

        list($argv, $options) = $this->parseOutOurOptions($argv, $optionDefaultValues);

        $helpArg = $this->getHelpArgValue($argv);
        if (!empty($helpArg)) {
            return $this->help($helpArg);
        }

        $commandList = $this->separateProjectAndGetCommandList($argv, $home, $options);
        if (empty($commandList)) {
            return 1;
        }
        return $this->runCommandList($commandList, $options);
    }

    /**
     * Returns the first argument after `help`, or the
     * first argument if `--help` is present. Otherwise,
     * returns an empty string.
     */
    public function getHelpArgValue($argv)
    {
        $hasHelp = false;
        $helpArg = '';

        foreach ($argv as $arg) {
            if (($arg == 'help') || ($arg == '--help') || ($arg == '-h')) {
                $hasHelp = true;
            } elseif (($arg[0] != '-') && empty($helpArg)) {
                $helpArg = $arg;
            }
        }

        if (!$hasHelp) {
            return false;
        }

        if (empty($helpArg)) {
            return 'help';
        }

        return $helpArg;
    }

    public function help($helpArg)
    {
        $helpFile = dirname(__DIR__) . '/help/' . $helpArg;

        if (!file_exists($helpFile)) {
            print "No help available for '$helpArg'\n";
            return 1;
        }

        $helpContents = file_get_contents($helpFile);
        print $helpContents;
        return 0;
    }

    /**
     * Set up output redirection. Used by tests.
     */
    public function setOutputFile($outputFile)
    {
        $this->outputFile = $outputFile;
    }

    /**
     * Figure out everything we're going to do, but don't do any of it
     * yet, just return the command objects to run.
     */
    public function parseArgvAndGetCommandList($argv, $home)
    {
        $optionDefaultValues = $this->getDefaultOptionValues($home);
        $optionDefaultValues = $this->overlayEnvironmentValues($optionDefaultValues);

        list($argv, $options) = $this->parseOutOurOptions($argv, $optionDefaultValues);
        return $this->separateProjectAndGetCommandList($argv, $home, $options);
    }

    /**
     * Figure out everything we're going to do, but don't do any of it
     * yet, just return the command objects to run.
     */
    public function separateProjectAndGetCommandList($argv, $home, $options)
    {
        list($command, $projects, $composerArgs) = $this->separateProjectsFromArgs($argv, $options);

        // If command was unknown, then exit with an error message
        if (empty($command)) {
            print "Unknown command: " . implode(' ', $composerArgs) . "\n";
            exit(1);
        }

        $commandList = $this->getCommandsToExec($command, $composerArgs, $projects, $options);
        return $commandList;
    }

    /**
     * Run all of the commands in a list.  Abort early if any fail.
     *
     * @param array $commandList An array of CommandToExec
     * @return integer
     */
    public function runCommandList($commandList, $options)
    {
        foreach ($commandList as $command) {
            $exitCode = $command->run($this->outputFile);
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
    public function getCommandsToExec($command, $composerArgs, $projects, $options)
    {
        $execPath = $options['composer-path'];

        // Call requireCommand, updateCommand, or removeCommand, as appropriate.
        $methodName = "{$command}Command";
        if (method_exists($this, $methodName)) {
            return $this->$methodName($execPath, $composerArgs, $projects, $options);
        } else {
            // If there is no specific implementation for the requested command, then call 'generalCommand'.
            return $this->generalCommand($command, $execPath, $composerArgs, $projects, $options);
        }
    }

    /**
     * Return our list of default option values, with paths relative to
     * the provided home directory.
     * @param string $home The composer home directory
     * @return array
     */
    public function getDefaultOptionValues($home)
    {
        return array(
            'composer' => false,
            'composer-path' => 'composer',
            'base-dir' => "$home/global",
            'bin-dir' => "$home/vendor/bin",
            'stability' => false,
        );
    }

    /**
     * Replace option default values with the corresponding
     * environment variable value, if it is set.
     */
    protected function overlayEnvironmentValues($defaults)
    {
        foreach ($defaults as $key => $value) {
            $envKey = 'CGR_' . strtoupper(strtr($key, '-', '_'));
            $envValue = getenv($envKey);
            if ($envValue) {
                $defaults[$key] = $envValue;
            }
        }
        return $defaults;
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
        $argv0 = array_shift($argv);
        $options['composer'] = (strpos($argv0, 'composer') !== false);
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
    public function separateProjectsFromArgs($argv, $options)
    {
        $cgrCommands = array('info', 'require', 'update', 'remove', 'extend');
        $command = 'require';
        $composerArgs = array();
        $projects = array();
        $globalMode = !$options['composer'];
        foreach ($argv as $arg) {
            if ($arg[0] == '-') {
                // Any flags (first character is '-') will just be passed
                // through to to composer. Flags interpreted by cgr have
                // already been removed from $argv.
                $composerArgs[] = $arg;
            } elseif (strpos($arg, '/') !== false) {
                // Arguments containing a '/' name projects.  We will split
                // the project from its version, allowing the separator
                // character to be either a '=' or a ':', and then store the
                // result in the $projects array.
                $projectAndVersion = explode(':', strtr($arg, '=', ':'), 2) + array('', '');
                list($project, $version) = $projectAndVersion;
                $projects[$project] = $version;
            } elseif ($this->isComposerVersion($arg)) {
                // If an argument is a composer version, then we will alter
                // the last project we saw, attaching this version to it.
                // This allows us to handle 'a/b:1.0' and 'a/b 1.0' equivalently.
                $keys = array_keys($projects);
                $lastProject = array_pop($keys);
                unset($projects[$lastProject]);
                $projects[$lastProject] = $arg;
            } elseif ($arg == 'global') {
                // Make note if we see the 'global' command.
                $globalMode = true;
            } elseif ($command == 'extend') {
                $projects[$arg] = true;
            } else {
                // If we see any command other than 'global [require|update|remove]',
                // then we will pass *all* of the arguments through to
                // composer unchanged. We return an empty projects array
                // to indicate that this should be a pass-through call
                // to composer, rather than one or more calls to
                // 'composer require' to install global projects.
                if ((!$globalMode) || (!in_array($arg, $cgrCommands))) {
                    return array('', array(), $argv);
                }
                // Remember which command we saw
                $command = $arg;
            }
        }
        return array($command, $projects, $composerArgs);
    }

    /**
     * Provide a safer version of `composer global require`.  Each project
     * listed in $projects will be installed into its own project directory.
     * The binaries from each project will still be placed in the global
     * composer bin directory.
     *
     * @param string $execPath The path to composer
     * @param array $composerArgs Anything from the global $argv to be passed
     *   on to Composer
     * @param array $projects A list of projects to install, with the key
     *   specifying the project name, and the value specifying its version.
     * @param array $options User options from the command line; see
     *   $optionDefaultValues in the main() function.
     * @return array
     */
    public function requireCommand($execPath, $composerArgs, $projects, $options)
    {
        $stabilityCommands = array();
        if ($options['stability']) {
            $stabilityCommands = $this->configureProjectStability($execPath, $composerArgs, $projects, $options);
        }
        $requireCommands = $this->generalCommand('require', $execPath, $composerArgs, $projects, $options);
        return array_merge($stabilityCommands, $requireCommands);
    }

    public function extendCommand($execPath, $composerArgs, $projects, $options)
    {
        $projectToExtend = $this->getProjectToExtend($projects);
        if (!$projectToExtend) {
            print "No command to extend specified\n";
            exit(1);
        }
        array_shift($projects);

        $options['base-dir'] .= '/' . $projectToExtend;
        $options['extend-mode'] = true;
        if (!is_dir($options['base-dir'])) {
            print "Project $projectToExtend not found; try 'cgr require' first\n";
            exit(1);
        }

        return $this->requireCommand($execPath, $composerArgs, $projects, $options);
    }

    protected function getProjectToExtend($projects)
    {
        $keys = array_keys($projects);
        $project = array_shift($keys);

        return $project;
    }

    /**
     * General command handler.
     *
     * @param string $composerCommand The composer command to run e.g. require
     * @param string $execPath The path to composer
     * @param array $composerArgs Anything from the global $argv to be passed
     *   on to Composer
     * @param array $projects A list of projects to install, with the key
     *   specifying the project name, and the value specifying its version.
     * @param array $options User options from the command line; see
     *   $optionDefaultValues in the main() function.
     * @return array
     */
    public function generalCommand($composerCommand, $execPath, $composerArgs, $projects, $options)
    {
        $globalBaseDir = $options['base-dir'];
        $binDir = $options['bin-dir'];
        $extendMode = !empty($options['extend-mode']);
        $env = array("COMPOSER_BIN_DIR" => $binDir);
        $result = array();
        foreach ($projects as $project => $version) {
            $installLocation = $extendMode ? $globalBaseDir : "$globalBaseDir/$project";
            $projectWithVersion = $this->projectWithVersion($project, $version);
            $commandToExec = $this->buildGlobalCommand($composerCommand, $execPath, $composerArgs, $projectWithVersion, $env, $installLocation);
            $result[] = $commandToExec;
        }
        return $result;
    }

    /**
     * Remove command handler. Build an `rm -rf` command.
     *
     * @param string $execPath The path to composer (ignored)
     * @param array $composerArgs Anything from the global $argv to be passed
     *   on to Composer (ignored)
     * @param array $projects A list of projects to install, with the key
     *   specifying the project name, and the value specifying its version.
     * @param array $options User options from the command line; see
     *   $optionDefaultValues in the main() function.
     * @return array
     */
    public function removeCommand($execPath, $composerArgs, $projects, $options)
    {
        $globalBaseDir = $options['base-dir'];
        $env = array();
        $result = $this->generalCommand('remove', $execPath, $composerArgs, $projects, $options);
        foreach ($projects as $project => $version) {
            $installLocation = "$globalBaseDir/$project";
            $result[] = new CommandToExec('rm', array('-rf', $installLocation), $env, $installLocation);
        }
        return $result;
    }

    /**
     * Command handler for commands where the project should not be provided
     * as a parameter to Composer (e.g. 'update').
     *
     * @param string $composerCommand The composer command to run e.g. require
     * @param string $execPath The path to composer
     * @param array $composerArgs Anything from the global $argv to be passed
     *   on to Composer
     * @param array $projects A list of projects to install, with the key
     *   specifying the project name, and the value specifying its version.
     * @param array $options User options from the command line; see
     *   $optionDefaultValues in the main() function.
     * @return array
     */
    public function noProjectArgCommand($composerCommand, $execPath, $composerArgs, $projects, $options)
    {
        $globalBaseDir = $options['base-dir'];
        $binDir = $options['bin-dir'];
        $env = array("COMPOSER_BIN_DIR" => $binDir);
        $result = array();
        foreach ($projects as $project => $version) {
            $installLocation = "$globalBaseDir/$project";
            $commandToExec = $this->buildGlobalCommand($composerCommand, $execPath, $composerArgs, '', $env, $installLocation);
            $result[] = $commandToExec;
        }
        return $result;
    }

    /**
     * If --stability VALUE is provided, then run a `composer config minimum-stability VALUE`
     * command to configure composer.json appropriately.
     *
     * @param string $execPath The path to composer
     * @param array $composerArgs Anything from the global $argv to be passed
     *   on to Composer
     * @param array $projects A list of projects to install, with the key
     *   specifying the project name, and the value specifying its version.
     * @param array $options User options from the command line; see
     *   $optionDefaultValues in the main() function.
     * @return array
     */
    public function configureProjectStability($execPath, $composerArgs, $projects, $options)
    {
        $globalBaseDir = $options['base-dir'];
        $stability = $options['stability'];
        $result = array();
        $env = array();

        foreach ($projects as $project => $version) {
            $installLocation = "$globalBaseDir/$project";
            FileSystemUtils::mkdirParents($installLocation);
            if (!file_exists("$installLocation/composer.json")) {
                file_put_contents("$installLocation/composer.json", '{}');
            }
            $result[] = $this->buildConfigCommand($execPath, $composerArgs, 'minimum-stability', $stability, $env, $installLocation);
        }
        return $result;
    }

    /**
     * Run `composer info`. Not only do we want to display the information of
     * the "global" Composer project, we also want to get the infomation of
     * all the "isolated" projects installed via cgr in ~/.composer/global.
     *
     * @param string $command The path to composer
     * @param array $composerArgs Anything from the global $argv to be passed
     *   on to Composer
     * @param array $projects A list of projects to update.
     * @param array $options User options from the command line; see
     *   $optionDefaultValues in the main() function.
     * @return array
     */
    public function infoCommand($execPath, $composerArgs, $projects, $options)
    {
        // If 'projects' list is empty, make a list of everything currently installed
        if (empty($projects)) {
            $projects = FileSystemUtils::allInstalledProjectsInBaseDir($options['base-dir']);
            $projects = $this->flipProjectsArray($projects);
        }
        return $this->generalCommand('info', $execPath, $composerArgs, $projects, $options);
    }

    /**
     * Run `composer global update`. Not only do we want to update the
     * "global" Composer project, we also want to update all of the
     * "isolated" projects installed via cgr in ~/.composer/global.
     *
     * @param string $command The path to composer
     * @param array $composerArgs Anything from the global $argv to be passed
     *   on to Composer
     * @param array $projects A list of projects to update.
     * @param array $options User options from the command line; see
     *   $optionDefaultValues in the main() function.
     * @return array
     */
    public function updateCommand($execPath, $composerArgs, $projects, $options)
    {
        // If 'projects' list is empty, make a list of everything currently installed
        if (empty($projects)) {
            $projects = FileSystemUtils::allInstalledProjectsInBaseDir($options['base-dir']);
            $projects = $this->flipProjectsArray($projects);
        }
        return $this->noProjectArgCommand('update', $execPath, $composerArgs, $projects, $options);
    }

    /**
     * Convert from an array of projects to an array where the key is the
     * project name, and the value (version) is an empty string.
     *
     * @param string[] $projects
     * @return array
     */
    public function flipProjectsArray($projects)
    {
        return array_map(function () {
            return '';
        }, array_flip($projects));
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
     * Generate command string to call `composer COMMAND` to install one project.
     *
     * @param string $command The path to composer
     * @param array $composerArgs The arguments to pass to composer
     * @param string $projectWithVersion The project:version to install
     * @param array $env Environment to set prior to exec
     * @param string $installLocation Location to install the project
     * @return CommandToExec
     */
    public function buildGlobalCommand($composerCommand, $execPath, $composerArgs, $projectWithVersion, $env, $installLocation)
    {
        $projectSpecificArgs = array("--working-dir=$installLocation", $composerCommand);
        if (!empty($projectWithVersion)) {
            $projectSpecificArgs[] = $projectWithVersion;
        }
        $arguments = array_merge($composerArgs, $projectSpecificArgs);
        return new CommandToExec($execPath, $arguments, $env, $installLocation);
    }

    /**
     * Generate command string to call `composer config KEY VALUE` to install one project.
     *
     * @param string $execPath The path to composer
     * @param array $composerArgs The arguments to pass to composer
     * @param string $key The config item to set
     * @param string $value The value to set the config item to
     * @param array $env Environment to set prior to exec
     * @param string $installLocation Location to install the project
     * @return CommandToExec
     */
    public function buildConfigCommand($execPath, $composerArgs, $key, $value, $env, $installLocation)
    {
        $projectSpecificArgs = array("--working-dir=$installLocation", 'config', $key, $value);
        $arguments = array_merge($composerArgs, $projectSpecificArgs);
        return new CommandToExec($execPath, $arguments, $env, $installLocation);
    }

    /**
     * Identify an argument that could be a Composer version string.
     *
     * @param string $arg The argument to test
     * @return boolean
     */
    public function isComposerVersion($arg)
    {
        // Allow for 'dev-master', et. al.
        if (substr($arg, 0, 4) == 'dev-') {
            return true;
        }
        $specialVersionChars = array('^', '~', '<', '>');
        return is_numeric($arg[0]) || in_array($arg[0], $specialVersionChars);
    }
}
