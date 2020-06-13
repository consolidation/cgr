# Consolidation\Cgr

Provide a safer alternative to `composer global require`.

[![Build Status](https://travis-ci.org/consolidation/cgr.svg?branch=main)](https://travis-ci.org/consolidation/cgr) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/consolidation/cgr/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/consolidation/cgr/?branch=main) [![Coverage Status](https://coveralls.io/repos/github/consolidation/cgr/badge.svg?branch=main)](https://coveralls.io/github/consolidation/cgr?branch=main) [![License](https://poser.pugx.org/consolidation/cgr/license)](https://packagist.org/packages/consolidation/cgr)

## Component Status

Cgr has been used in real-world environments for several months with no reported problems; however, see 'Limitations', below.

## Motivation

The Composer `global require` command is a recommended installation technique for many PHP commandline tools; however, users who install tools in this way risk encountering installation failures caused by dependency conflicts between different projects. The cgr script behaves similarly to `composer global require`, using `composer require` to install a global-per-user copy of a commandline tool, but in an isolated location that will not experience dependency conflicts with other globally-installed tools.

**The cgr script is unrelated to security; it is not any more, nor any less secure than installing via composer global require.** 

Composer itself recommends `composer global require` as a "convenience" command for installing commandline tools.  Unfortunately, this recommendation is at odds with the basic assumption of Composer, which is that every project's dependencies should be managed independently.  The Composer `global` command creates a single "global" project; projects installed via `composer global require` will all be installed in this location, and their dependencies will all be merged.  This means that conflicts can arise between two independent projects that were never designed to work together, and have no need for their dependencies to be combined into a single autoloader.  When this sort of situation does arise, it is often very difficult for beginners to diagnose.

This script, called `cgr`, is named after "composer global require", the Composer command that it emulates.  It offers a replacement mechanism for installing PHP commandline tools globally that is functionally equivalent (nearly) to the existing command, but much safer.  The Cgr script will make a separate directory for each project installed; by default, the installation location is `~/.composer/global/org/project`.  Any binary scripts listed in the installed project's composer.json file will be installed to the standard Composer bin directory, `~/.composer/vendor/bin`.

## Installation and Usage

Because the cgr script has no dependencies of its own, it is safe to install via the Composer `global require` command:

`composer global require consolidation/cgr`

If you have not already done so, you will also need to add the `vendor/bin` from the Composer home directory to your $PATH.  Thereafter, you may subsitute `cgr` for any commandline tool whose installation instructions recommends the use of Composer `global require`.

To add the correct bin directory to your PATH:
```
PATH="$(composer config -g home)/vendor/bin:$PATH"
```

Example:

`cgr drush/drush`

Unlike the composer global require command, it is possible using cgr to set the minimum stability for a project before installing it.  This is done in the same way as the `composer create-project` command:

`cgr --stability RC pantheon-systems/terminus 1.0.0-alpha2`

The behavior of the cgr script can be customized with commandline options and environment variables.

Option           | Environment Variable | Description
-----------------|----------------------|-----------------------------------
--composer-path  | CGR_COMPOSER_PATH    | The path to the Composer binary.
--base-dir       | CGR_BASE_DIR         | Where to store "global" projects.
--bin-dir        | CGR_BIN_DIR          | Where to install project binaries.

If these variables are not defined, then cgr uses the value of the `COMPOSER_HOME` environment variable as the base directory to use as described in the [Composer documentation on environment variables](https://getcomposer.org/doc/03-cli.md#composer-home).

To configure cgr to install binaries to ~/bin, add the following to your ~/.bashrc file:

`export CGR_BIN_DIR=$HOME/bin`

You may select any directory you like for the `CGR_BIN_DIR`, as long as it is in your $PATH.

## Display information

To display the information of a project, run:

`cgr info drush/drush`

To display the information of all projects installed via 'cgr', run:

`cgr info`

## Updating and Removing

To update a project that you installed with `cgr`, run:

`cgr update drush/drush`

To update everything installed by `cgr`, run:

`cgr update`

To remove a project:

`cgr remove drush/drush`

To update or remove cgr itself, run `composer global update consolidation/cgr` or `composer global remove cgr`.  Note that removing cgr has no effect on the commands you installed with cgr; they will remain installed and functional.

## Troubleshooting

If you find that `cgr` is still behaving like a standard Composer `global require` command, double-check the settings of your $PATH variable, and use `which cgr` and `alias cgr` to deterime whether or not this script is being selected by your shell. It is possible that cgr may conflict with some other tool; for example, [the oh-my-zsh project defines a cgr alias](https://github.com/robbyrussell/oh-my-zsh/blob/0d45e771c8d3d1f7c465be465fcbdb4169141347/plugins/composer/composer.plugin.zsh#L46). If this is an issue for you, either `unalias cgr`, or perhaps add `alias cgrx="$HOME/.composer/vendor/bin/cgr"` to run this experimental tool as `cgrx`.

## Limitations

Composer will also load Composer Plugins from the "global" Composer project. This is rare; however, if you would like to install a Composer Installer globally, then you must use the `composer global require` command directly. The cgr script isolates the projects it installs from each other to avoid potential conflicts between dependencies; this isolation also makes any Composer Plugins unavailable in the global context.

## Alternative Solutions

### Manual Use of Native Composer Features

The cgr script maintains the convenience of automatically managing the global installation location for you; however, if this is not desired, you may simply run commands similar to:

`COMPOSER_BIN_DIR=$HOME/bin composer require org/project:~1.0`

If you go this route, you will need to set up your install location manually using `mkdir` and `cd` as necessary prior to running `composer require`. You cannot simply set COMPOSER_BIN_DIR globally, as doing this would cause the binaries from local projects to be installed into your global bin directory, which would, of course, not be desirable.

In a Continuous Integration script, the following construct is useful:

`/usr/bin/env COMPOSER_BIN_DIR=$HOME/bin composer --working-dir=$HOME/project require org/project:~1.0`

Change the installation directory (`$HOME/project`) to match the project being installed, so that every project is installed in its own separate location.

### Composer Bin Plugin

The Composer plugin [bamarni/composer-bin-plugin](https://github.com/bamarni/composer-bin-plugin) offers a similar way to manage isolated installation of binary tools by defining separate named installation locations. This gives a convenient way to install multiple projects together (e.g. install [Robo](https://robo.li) along with external projects providing additional Robo tasks in a 'robo' project).

## Future Development

It is hoped that this tool will be an interim solution, until changes in Composer make it unnecessary.  See [the Composer issue](https://github.com/composer/composer/issues/5390#issuecomment-224011226) for updates.
