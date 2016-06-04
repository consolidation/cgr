<?php

namespace Consolidation\Cgr;

/**
 * Hold a command string + envrionment to execute.
 */
class CommandToExec
{
    protected $command;
    protected $arguments;
    protected $env;
    protected $dir;

    /**
     * Hold some command values to later exec
     */
    public function __construct($command, $arguments, $env = array(), $dir = '')
    {
        $this->command = $command;
        $this->arguments = $arguments;
        $this->env = $env;
        $this->dir = $dir;
    }

    /**
     * Generate a single command string.
     */
    public function getCommandString()
    {
        $escapedArgs = array_map(function ($item) {
            return escapeshellarg($item);
        }, $this->arguments);
        return $this->command . ' ' . implode(' ', $escapedArgs);
    }

    /**
     * Run our command. Set up the environment, as needed, ensuring that
     * it is restored at the end of the run.
     */
    public function run($stdoutFile = '')
    {
        $commandString = $this->getCommandString();
        $origEnv = static::applyEnv($this->env);
        $origDir = static::applyDir($this->dir);
        $exitCode = static::runCommand($commandString, $stdoutFile);
        static::applyEnv($origEnv);
        static::applyDir($origDir);
        return $exitCode;
    }

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
     * Apply a set of environment variables; return the original
     * value of any value that is set, to avoid polluting the environment.
     *
     * @param array $env An array of key:value pairs
     * @return array
     */
    public static function applyEnv($env)
    {
        $orig = array();
        foreach ($env as $key => $value) {
            $orig[$key] = getenv($key);
            static::setEnvValue($key, $value);
        }
        return $orig;
    }

    /**
     * Set or un-set one environment variable
     *
     * @param string $key The environment variable to set or unset
     * @param mixed $value THe value to set the variable to, or false to unset.
     */
    public static function setEnvValue($key, $value)
    {
        if ($value === false) {
            putenv($key);
            return;
        }
        putenv("$key=$value");
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
     * Run a single command.
     *
     * @param string $commandString
     * @return integer
     */
    public static function runCommand($commandString, $stdoutFile = '')
    {
        $stdout = STDOUT;
        $stderr = STDERR;
        if (!empty($stdoutFile)) {
            $stdout = array("file", $stdoutFile, "a");
            $stderr = $stdout;
        }
        $process = proc_open($commandString, array(0 => STDIN, 1 => $stdout, 2 => $stderr), $pipes);
        $procStatus = proc_get_status($process);
        $exitCode = proc_close($process);
        return ($procStatus["running"] ? $exitCode : $procStatus["exitcode"]);
    }
}
