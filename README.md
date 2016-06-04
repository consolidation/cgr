# Consolidation\Cgr

Provide a safe alternative to `composer global require`.

[![Build Status](https://travis-ci.org/consolidation-org/cgr.svg?branch=master)](https://travis-ci.org/consolidation-org/cgr) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/consolidation-org/cgr/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/consolidation-org/cgr/?branch=master) [![Coverage Status](https://coveralls.io/repos/github/consolidation-org/cgr/badge.svg?branch=master)](https://coveralls.io/github/consolidation-org/cgr?branch=master) [![License](https://poser.pugx.org/consolidation/cgr/license)](https://packagist.org/packages/consolidation/cgr)

## Component Status

Still new; code coverage is good, but only lightly used in real-world environments.

## Motivation

The Composer `global require` command is a recommended installation technique for many PHP commandline tools. Composer itself recommends this "convenience" command for exactly this purpose.  Unfortunately, this recommendation is at odds with the basic assumption of Composer, which is that every project's dependencies should be managed independently.  The Composer `global` command creates a single "global" project; projects installed via `composer global require` will all be installed in this location, and their dependencies will all be merged.  This means that conflicts can arise between two independent projects that were never designed to work together, and have no need for their dependencies to be combined into a single autoloader.  When this sort of situation does arise, it is often very difficult for beginners to diagnose.

This script, called `cgr`, is named after "composer global require", the Composer command that it emulates.  It offers a replacement mechanism for installing PHP commandline tools globally that is functionally equivalent (nearly) to the existing command, but much safer.  The Cgr script will make a separate directory for each project installed; by default, the installation location is `~/.composer/global/org/project`.  Any binary scripts listed in the installed project's composer.json file will be installed to the standard Composer bin directory, `~/.composer/vendor/bin`.

## Installation and Usage

Because the cgr script has no dependencies of its own, it is safe to install via the Composer `global require` command:

`composer global require consolidation/cgr`

If you have not already done so, you will also need to add `~/.composer/vendor/bin` to your $PATH.  Thereafter, you may subsitute `cgr` for any commandline tool whose installation instructions recommends the use of Composer `global require`.

Example:

`cgr drush/drush`

The behavior of the cgr script can be customized with commandline options and environment variables.

Option           | Environment Variable | Description
-----------------|----------------------|-----------------------------------
--composer-path  | CGR_COMPOSER_PATH    | The path to the Composer binary.
--base-dir       | CGR_BASE_DIR         | Where to store "global" projects.
--bin-dir        | CGR_BIN_DIR          | Where to install project binaries.

To configure cgr to install binaries to ~/bin, add the following to your ~/.bashrc file:

`export CGR_BIN_DIR=$HOME/bin`

## Limitations

Composer will also load Composer Plugins from the "global" Composer project. This is rare; however, if you would like to install a Composer Installer globally, then you must use the `composer global require` command directly. The cgr script isolates the projects it installs from each other to avoid potential conflicts between dependencies; this isolation also makes any Composer Plugins unavailable in the global context.

## Alternative Solutions

The cgr script maintains the convenience of automatically managing the global installation location for you; however, if this is not desired, you may simply run commands similar to:

`COMPOSER_BIN_DIR=$HOME/bin composer require org/project:~1.0`

If you go this route, you will need to set up your install location manually using `mkdir` and `cd` as necessary prior to running `composer require`. You cannot simply set COMPOSER_BIN_DIR globally, as doing this would cause the binaries from local projects to be installed into your global bin directory, which would, of course, not be desirable.

