#!/bin/sh

# This script is provided as a surrogate for `composer` during tests.
# cgr will set the current working directory to the installation location
# before running this script; we will deposit an output file containing
# the arguments we were called with.
echo $@ > output.txt
