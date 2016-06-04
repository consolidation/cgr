<?php

/**
 * This script is provided as a surrogate for `composer` during tests.
 * It dumps its arguments and environment variables.  We can't simply
 * use printenv directly, as it would complain about invalid arguments.
 */
array_shift($argv);
print implode(' ', $argv) . PHP_EOL;
passthru('printenv COMPOSER_BIN_DIR');
