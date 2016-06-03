# Consolidation\Cgr

Provide a safe alternative to `composer global require`.

[![Build Status](https://travis-ci.org/consolidation-org/cgr.svg?branch=master)](https://travis-ci.org/consolidation-org/cgr) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/consolidation-org/cgr/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/consolidation-org/cgr/?branch=master) [![Coverage Status](https://coveralls.io/repos/github/consolidation-org/cgr/badge.svg?branch=master)](https://coveralls.io/github/consolidation-org/cgr?branch=master) [![License](https://poser.pugx.org/consolidation/cgr/license)](https://packagist.org/packages/consolidation/cgr)

## Component Status

Still new; lightly tested.

## Motivation

The Composer `global require` command is a recommended installation technique for many PHP commandline tools. Composer itself recommends this "convenience" command for exactly this purpose.  Unfortunately, this recommendation is at odds with the basic assumption of Composer, which is that every project's dependencies should be managed independently.  The Composer `global` command creates a single "global" project; projects installed via `composer global require` will all be installed in this location, and their dependencies will all be merged.  This means that conflicts can arise between two independent projects that were never designed to work together, and have no need for their dependencies to be combined into a single autoloader.  When this sort of situation does arise, it is often very difficult for beginners to diagnose.

This script, called "Cgr", which is short for "composer global require", offers a replacement mechanism for installing PHP commandline tools globally.  The Cgr script will make a separate directory for each project installed; by default, the installation location is `~/.composer/global/org/project`.  Any binary scripts listed in the installed project's composer.json file will be installed to the standard Composer bin directory, `~/.composer/vendor/bin`.

## Installation and Usage

Because the cgr script has no dependencies of its own, it is safe to install via the Composer `global require` command:

`composer global require consolidation/cgr`

If you have not already done so, you will also need to add `~/.composer/vendor/bin` to your $PATH.  Thereafter, you may subsitute `cgr` for any commandline tool whose installation instructions recommends the use of `composer global require`.
