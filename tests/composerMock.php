<?php

/**
 * This script is provided as a surrogate for `composer` during tests.
 * cgr will set the current working directory to the installation location
 * before running this script; we will deposit an output file containing
 * the arguments we were called with.
 */
file_put_contents('output.txt', implode(' ', $argv));
