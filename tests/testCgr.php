<?php
namespace Consolidation\Cgr;

class CgrTests extends \PHPUnit_Framework_TestCase
{
    protected $application;
    protected $workDir;
    protected static $tempDir;

    static function setUpBeforeClass()
    {
        static::$tempDir = static::tempdir();
    }

    function setUp()
    {
        $this->application = new Application();
        $this->workDir = static::tempdir(static::$tempDir);
        chdir($this->workDir);
    }

    function tearDown()
    {
        static::fileDeleteRecursive($this->workDir);
    }

    static function tearDownAfterClass()
    {
        static::fileDeleteRecursive(static::$tempDir);
    }

    public function testApplicationCommandStringsValues()
    {
        $argvCgrMultipleProjectForms = array(
            'cgr',
            'x/y:1.0',
            'a/b=~2',
            'p/q',
            '^3',
            'd/e',
        );
        $expectedCgrMultipleProjectForms = <<< EOT
composer '--working-dir=/home/user/.composer/global/x/y' 'require' 'x/y:1.0'
composer '--working-dir=/home/user/.composer/global/a/b' 'require' 'a/b:~2'
composer '--working-dir=/home/user/.composer/global/p/q' 'require' 'p/q:^3'
composer '--working-dir=/home/user/.composer/global/d/e' 'require' 'd/e'
EOT;

        $argvGlobalUpdate = array(
            'composer',
            'global',
            'update',
        );
        $expectedGlobalUpdate = <<< EOT
composer 'global' 'update'
EOT;

        $argvComposerInit = array(
            'composer',
            'init',
            "--name=test/test",
            '--no-interaction',
        );
        $expectedComposerInit = <<< EOT
composer 'init' '--name=test/test' '--no-interaction'
EOT;

        return array(
            array(
                $argvCgrMultipleProjectForms,
                $expectedCgrMultipleProjectForms,
            ),
            array(
                $argvGlobalUpdate,
                $expectedGlobalUpdate,
            ),
            array(
                $argvComposerInit,
                $expectedComposerInit,
            ),
        );
    }

    /**
     * Unit tests on the application.  We parse several sets of
     * arguments, and check to see if the generate appropriate
     * command strings.
     *
     * @dataProvider testApplicationCommandStringsValues
     */
    public function testApplicationCommandStrings($argv, $expected)
    {
        $commandList = $this->application->parseArgvAndGetCommandList($argv, '/home/user');
        $commandStrings = array();
        foreach ($commandList as $command) {
            $commandStrings[] = $command->getCommandString();
        }
        $actual = implode("\n", $commandStrings);
        $this->assertEquals($expected, $actual);
    }

    public function testApplicationOutputValues()
    {
        $argvEcho = array(
            'cgr',
            '--composer-path',
            'echo',
            '--cgr-output',
            $this->workDir . '/output.txt',
            'x/y:1.0',
            'a/b=~2'
        );
        $expectedEcho = <<< EOT
--working-dir={workdir}/.composer/global/x/y require x/y:1.0
--working-dir={workdir}/.composer/global/a/b require a/b:~2
EOT;

        $argvEchoPrintenv = array(
            'cgr',
            '--composer-path',
            'php ' . __DIR__ . '/echoPrintenv.php',
            '--bin-dir',
            '/a/b/.composer/bin',
            'a/b:1.0'
        );
        $expectedEchoPrintenv = <<< EOT
--working-dir={workdir}/.composer/global/a/b require a/b:1.0
/a/b/.composer/bin
EOT;

        return array(
            array(
                $argvEcho,
                $expectedEcho,
            ),
            array(
                $argvEchoPrintenv,
                $expectedEchoPrintenv,
            ),
        );
    }

    /**
     * Functional test using a mocked application: we call a
     * script that echos its arguments and prints the COMPOSER_BIN_DIR
     * environment variable.
     *
     * @dataProvider testApplicationOutputValues
     */
    public function testApplicationOutput($argv, $expected)
    {
        $argv[] = '--cgr-output';
        $argv[] = $this->workDir . '/output.txt';

        $exitCode = $this->application->run($argv, $this->workDir);
        $this->assertFileExists($this->workDir . '/output.txt', 'Output file created.');
        $output = file_get_contents($this->workDir . '/output.txt');
        $expected = str_replace('{workdir}', $this->workDir, $expected);
        $this->assertEquals($expected, rtrim($output));
    }

    /**
     * Functional test with the real composer executable.  Use cgr to
     * require cgr, although we do so with --no-update to avoid pointlessly
     * downloading files we do not actually need.  We therefore only
     * test to see whether the `composer.json` file is correctly updated;
     * we cannot test to see if the cgr binary is correctly installed
     * in the appropriate bin directory, as this step is not done.
     */
    public function testApplicationWithComposer()
    {
        $argv = array(
            'composer',
            '--no-update',
            'consolidation/cgr:1.0',
        );
        $exitCode = $this->application->run($argv, $this->workDir);
        $this->assertEquals(0, $exitCode);
        $this->assertFileExists($this->workDir . '/.composer/global/consolidation/cgr/composer.json', 'composer.json created');
        $composerJson = file_get_contents($this->workDir . '/.composer/global/consolidation/cgr/composer.json');
        $this->assertContains('"consolidation/cgr": "1.0"', $composerJson);
    }
    /**
     * Functional test with the real composer executable.  Use cgr to
     * require cgr, although we do so with --no-update to avoid pointlessly
     * downloading files we do not actually need.  We therefore only
     * test to see whether the `composer.json` file is correctly updated;
     * we cannot test to see if the cgr binary is correctly installed
     * in the appropriate bin directory, as this step is not done.
     */
    public function testApplicationWithComposerPassthruCommand()
    {
        $argv = array(
            'composer',
            'init',
            "--name=test/test",
            '--no-interaction',
            '--working-dir=' . $this->workDir,
            '--cgr-output',
            $this->workDir . '/output.txt',
        );
        $exitCode = $this->application->run($argv, $this->workDir);
        $this->assertEquals(0, $exitCode);
        $this->assertFileExists($this->workDir . '/output.txt', 'Output file created.');
        $this->assertFileExists($this->workDir . '/composer.json', 'composer.json created');
        $composerJson = file_get_contents($this->workDir . '/composer.json');
        $expected = <<<EOT
{
    "name": "test/test",
    "require": {}
}
EOT;
        $this->assertEquals($expected, rtrim($composerJson));
    }

    static function tempdir($baseDir = false, $prefix = '')
    {
        $tempfile = tempnam($baseDir,$prefix);
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        mkdir($tempfile);
        if (is_dir($tempfile)) {
            return $tempfile;
        }
    }

    static function fileDeleteRecursive($dir) {
      // Do not delete symlinked files, only unlink symbolic links
      if (is_link($dir)) {
        return unlink($dir);
      }
      // Allow to delete symlinks even if the target doesn't exist.
      if (!is_link($dir) && !file_exists($dir)) {
        return true;
      }
      if (!is_dir($dir)) {
        @chmod($dir, 0777);
        return unlink($dir);
      }
      if (static::deleteDirContents($dir) === FALSE) {
        return false;
      }
      @chmod($dir, 0777);
      return rmdir($dir);
    }

    /**
     * Deletes the contents of a directory.
     *
     * This is essentially a copy of drush_delete_dir_contents().
     *
     * @param string $dir
     *   The directory to delete.
     * @param bool $force
     *   Whether or not to try everything possible to delete the contents, even if
     *   they're read-only. Defaults to FALSE.
     *
     * @return bool
     *   FALSE on failure, TRUE if everything was deleted.
     *
     * @see drush_delete_dir_contents()
     */
    static function deleteDirContents($dir) {
      $scandir = @scandir($dir);
      if (!is_array($scandir)) {
        return false;
      }
      foreach ($scandir as $item) {
        if ($item == '.' || $item == '..') {
          continue;
        }
        @chmod($dir, 0777);
        if (!static::fileDeleteRecursive($dir . '/' . $item)) {
          return true;
        }
      }
      return TRUE;
    }
}
