<?php
namespace Consolidation\Cgr;

class CgrTests extends \PHPUnit_Framework_TestCase
{
    protected $application;
    protected $workDir;

    function setUp() {
        $this->application = new Application();
        $this->workDir = $this->tempdir();
        chdir($this->workDir);
    }

    function tearDown() {
        $this->fileDeleteRecursive($this->workDir);
    }

    /**
     * Functional test using a mocked application: we call test.sh in
     * place of composer, and it dumps its arguments to a file.
     */
    public function testApplication()
    {
        $argv = array(
            'cgr',
            '--composer-path',
            __DIR__ . '/test.sh',
            'x/y:1.0',
            'a/b=~2'
        );
        $exitCode = $this->application->run($argv, $this->workDir);
        $this->assertEquals(0, $exitCode);
        $this->assertOutputFileContents('--working-dir={workdir}/.composer/global/a/b require a/b:~2', '/.composer/global/a/b');
        $this->assertOutputFileContents('--working-dir={workdir}/.composer/global/x/y require x/y:1.0', '/.composer/global/x/y');
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

    function tempdir($baseDir = false, $prefix = '')
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

    function fileDeleteRecursive($dir) {
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
      if ($this->deleteDirContents($dir) === FALSE) {
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
    function deleteDirContents($dir) {
      $scandir = @scandir($dir);
      if (!is_array($scandir)) {
        return false;
      }
      foreach ($scandir as $item) {
        if ($item == '.' || $item == '..') {
          continue;
        }
        @chmod($dir, 0777);
        if (!$this->fileDeleteRecursive($dir . '/' . $item)) {
          return true;
        }
      }
      return TRUE;
    }
}
