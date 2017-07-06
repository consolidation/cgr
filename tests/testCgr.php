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

    function composerHome()
    {
        return $this->workDir . '/.composer';
    }

    function tearDown()
    {
        static::fileDeleteRecursive($this->workDir);
    }

    static function tearDownAfterClass()
    {
        static::fileDeleteRecursive(static::$tempDir);
    }

    function createFixtures()
    {
        mkdir($this->composerHome());
        mkdir($this->composerHome() . '/global');
        mkdir($this->composerHome() . '/global/testorg');
        mkdir($this->composerHome() . '/global/testorg/testproject');
        file_put_contents($this->composerHome() . '/global/testorg/testproject/composer.json', '{}');
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
            'f/g',
            'dev-master',
        );
        $expectedCgrMultipleProjectForms = <<< EOT
composer '--working-dir={workdir}/.composer/global/x/y' require 'x/y:1.0'
composer '--working-dir={workdir}/.composer/global/a/b' require 'a/b:~2'
composer '--working-dir={workdir}/.composer/global/p/q' require 'p/q:^3'
composer '--working-dir={workdir}/.composer/global/d/e' require 'd/e'
composer '--working-dir={workdir}/.composer/global/f/g' require 'f/g:dev-master'
EOT;

        $argvCgrWithMinimumStability = array(
            'cgr',
            'x/y:1.0',
            '--stability',
            'dev',
        );
        $expectedCgrWithMinimumStability = <<<EOT
composer '--working-dir={workdir}/.composer/global/x/y' config minimum-stability dev
composer '--working-dir={workdir}/.composer/global/x/y' require 'x/y:1.0'
EOT;

        $argvCgrRemove = array(
            'cgr',
            'remove',
            'x/y',
            'a/b',
            'p/q',
            'd/e',
          );
          $expectedCgrRemove = <<<EOT
composer '--working-dir={workdir}/.composer/global/x/y' remove 'x/y'
composer '--working-dir={workdir}/.composer/global/a/b' remove 'a/b'
composer '--working-dir={workdir}/.composer/global/p/q' remove 'p/q'
composer '--working-dir={workdir}/.composer/global/d/e' remove 'd/e'
rm -rf '{workdir}/.composer/global/x/y'
rm -rf '{workdir}/.composer/global/a/b'
rm -rf '{workdir}/.composer/global/p/q'
rm -rf '{workdir}/.composer/global/d/e'
EOT;

          $argvCgrUpdate = array(
              'cgr',
              'update',
              'x/y',
              'a/b',
              'p/q',
              'd/e',
          );
          $expectedCgrUpdate = <<<EOT
composer '--working-dir={workdir}/.composer/global/x/y' update
composer '--working-dir={workdir}/.composer/global/a/b' update
composer '--working-dir={workdir}/.composer/global/p/q' update
composer '--working-dir={workdir}/.composer/global/d/e' update
EOT;

          $argvCgrUpdateWithoutArgs = array(
              'cgr',
              'update',
          );
          $expectedCgrUpdateWithoutArgs = <<<EOT
composer '--working-dir={workdir}/.composer/global/testorg/testproject' update
EOT;

          $argvCgrInfo = array(
              'cgr',
              'info',
              'x/y',
              'a/b',
              'p/q',
              'd/e',
          );
          $expectedCgrInfo = <<<EOT
composer '--working-dir={workdir}/.composer/global/x/y' info 'x/y'
composer '--working-dir={workdir}/.composer/global/a/b' info 'a/b'
composer '--working-dir={workdir}/.composer/global/p/q' info 'p/q'
composer '--working-dir={workdir}/.composer/global/d/e' info 'd/e'
EOT;

          $argvCgrInfoWithoutArgs = array(
              'cgr',
              'info',
          );
          $expectedCgrInfoWithoutArgs = <<<EOT
composer '--working-dir={workdir}/.composer/global/testorg/testproject' info 'testorg/testproject'
EOT;

        return array(
            array(
                $argvCgrMultipleProjectForms,
                $expectedCgrMultipleProjectForms,
            ),
            array(
                $argvCgrWithMinimumStability,
                $expectedCgrWithMinimumStability,
            ),
            array(
                $argvCgrRemove,
                $expectedCgrRemove,
            ),
            array(
                $argvCgrUpdate,
                $expectedCgrUpdate,
            ),
            array(
                $argvCgrUpdateWithoutArgs,
                $expectedCgrUpdateWithoutArgs,
            ),
            array(
                $argvCgrInfo,
                $expectedCgrInfo,
            ),
            array(
                $argvCgrInfoWithoutArgs,
                $expectedCgrInfoWithoutArgs,
            ),
        );
    }

    public function testFixtures()
    {
        $this->createFixtures();
        $directories = FileSystemUtils::listDirectories($this->composerHome() . '/global');
        $this->assertEquals('testorg', implode(',', $directories));
        $directories = FileSystemUtils::listDirectories($this->composerHome() . '/global/testorg');
        $this->assertEquals('testproject', implode(',', $directories));
        $projects = FileSystemUtils::allInstalledProjectsInBaseDir($this->composerHome() . '/global');
        $this->assertEquals('testorg/testproject', implode(',', $projects));
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
        $this->createFixtures();
        $home = $this->composerHome();
        $commandList = $this->application->parseArgvAndGetCommandList($argv, $home);
        $commandStrings = array();
        foreach ($commandList as $command) {
            $commandStrings[] = $command->getCommandString();
        }
        $actual = implode("\n", $commandStrings);
        $actual = str_replace($this->workDir, '{workdir}', $actual);
        $this->assertEquals($expected, $actual);
    }

    public function testApplicationOutputValues()
    {
        $argvEcho = array(
            'cgr',
            '--composer-path',
            'echo',
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
            '/p/q/.composer/bin',
            'a/b:1.0'
        );
        $expectedEchoPrintenv = <<< EOT
--working-dir={workdir}/.composer/global/a/b require a/b:1.0
/p/q/.composer/bin
EOT;

        $argvEchoPrintenvNoFlag = array(
            'cgr',
            '--composer-path',
            'php ' . __DIR__ . '/echoPrintenv.php',
            'a/b:1.0'
        );
        $expectedEchoPrintenvNoFlag = <<< EOT
--working-dir={workdir}/.composer/global/a/b require a/b:1.0
{workdir}/.composer/vendor/bin
EOT;

        $envEchoPrintenvNoFlag = array(
            'CGR_BIN_DIR' => '/home/user/bin',
        );
        $expectedEchoPrintenvNoFlagWithEnv = <<< EOT
--working-dir={workdir}/.composer/global/a/b require a/b:1.0
/home/user/bin
EOT;

        return array(
            array(
                $argvEcho,
                array(),
                $expectedEcho,
            ),
            array(
                $argvEchoPrintenv,
                array(),
                $expectedEchoPrintenv,
            ),
            array(
                $argvEchoPrintenvNoFlag,
                array(),
                $expectedEchoPrintenvNoFlag,
            ),
            array(
                $argvEchoPrintenvNoFlag,
                $envEchoPrintenvNoFlag,
                $expectedEchoPrintenvNoFlagWithEnv,
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
    public function testApplicationOutput($argv, $envArray, $expected)
    {
        $this->application->setOutputFile($this->workDir . '/output.txt');

        $env = new Env($envArray);
        $origEnv = $env->apply();
        $exitCode = $this->application->run($argv, $this->composerHome());
        $origEnv->apply();
        $this->assertFileExists($this->workDir . '/output.txt', 'Output file created.');
        $output = file_get_contents($this->workDir . '/output.txt');
        $output = str_replace($this->workDir, '{workdir}', $output);
        //$expected = str_replace('{workdir}', $this->workDir, $expected);
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
        $exitCode = $this->application->run($argv, $this->composerHome());
        $this->assertEquals(0, $exitCode);
        $this->assertFileExists($this->composerHome() . '/global/consolidation/cgr/composer.json', 'composer.json created');
        $composerJson = file_get_contents($this->composerHome() . '/global/consolidation/cgr/composer.json');
        $this->assertContains('"consolidation/cgr": "1.0"', $composerJson);
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
