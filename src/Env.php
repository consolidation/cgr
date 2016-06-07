<?php

namespace Consolidation\Cgr;

class Env
{
    protected $env;

    public function __construct($env)
    {
        $this->env = $env;
    }

    /**
     * Apply a set of environment variables; return the original
     * value of any value that is set, to avoid polluting the environment.
     *
     * @return Env
     */
    public function apply()
    {
        $orig = array();
        foreach ($this->env as $key => $value) {
            $orig[$key] = getenv($key);
            static::setEnvValue($key, $value);
        }
        return new Env($orig);
    }

    /**
     * Set or un-set one environment variable
     *
     * @param string $key The environment variable to set or unset
     * @param mixed $value THe value to set the variable to, or false to unset.
     */
    protected static function setEnvValue($key, $value)
    {
        if ($value === false) {
            putenv($key);
            return;
        }
        putenv("$key=$value");
    }
}
