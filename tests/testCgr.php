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

    public function testApplicationValues()
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

        return array(
            array(
                $argvCgrMultipleProjectForms,
                $expectedCgrMultipleProjectForms,
            ),
            array(
                $argvGlobalUpdate,
                $expectedGlobalUpdate,
            ),
        );
    }

    /**
     * Functional test using a mocked application: we call test.sh in
     * place of composer, and it dumps its arguments to a file.
     *
     * @dataProvider testApplicationValues
     */
    public function testApplication($argv, $expected)
    {
        $commandList = $this->application->parseArgvAndGetCommandList($argv, '/home/user');
        $commandStrings = array();
        foreach ($commandList as $command) {
            $commandStrings[] = $command->getCommandString();
        }
        $actual = implode("\n", $commandStrings);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Functional test using a mocked application: we call test.sh in
     * place of composer, and it dumps its arguments to a file.
     */
    public function testApplicationFunctional()
    {
        if (getenv('CI')) {
            $this->markTestSkipped('Functional test does not work on CI server. Output file not written to expected location.');
        }
        $argv = array(
            'cgr',
            '--composer-path',
            'php ' . __DIR__ . '/composerMock.php',
            'x/y:1.0',
            'a/b=~2'
        );

        $exitCode = $this->application->run($argv, $this->workDir);
        $this->assertEquals(0, $exitCode);
        $this->assertOutputFileContents(__DIR__ . '/composerMock.php --working-dir={workdir}/.composer/global/a/b require a/b:~2', '/.composer/global/a/b');
        $this->assertOutputFileContents(__DIR__ . '/composerMock.php --working-dir={workdir}/.composer/global/x/y require x/y:1.0', '/.composer/global/x/y');
    }

    /**
     * Test with the real composer executable.  Use cgr to install
     * cgr, and confirm that ~/.composer/vendor/bin is updated.
     */
    public function testApplicationWithComposer()
    {
        $argv = array(
            'cgr',
            '--no-update',
            'consolidation/cgr:1.0',
        );
        $exitCode = $this->application->run($argv, $this->workDir);
        $this->assertEquals(0, $exitCode);
        $this->assertTrue(file_exists($this->workDir . '/.composer/global/consolidation/cgr/composer.json'));
        $composerJson = file_get_contents($this->workDir . '/.composer/global/consolidation/cgr/composer.json');
        $this->assertContains('"consolidation/cgr": "1.0"', $composerJson);
    }

    function assertOutputFileContents($expected, $relativePath)
    {
        $expected = str_replace('{workdir}', $this->workDir, $expected);
        $contents = '';
        $outputFilePath = $this->workDir . $relativePath . '/output.txt';
        if (file_exists($outputFilePath)) {
            $contents = rtrim(file_get_contents($outputFilePath));
        }
        $this->assertEquals($expected, $contents);
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
