<?php
namespace Consolidation\Cgr;

class FileSystemUtilsTests extends \PHPUnit_Framework_TestCase
{

    public function testListDirectoriesr()
    {
        $directories = FileSystemUtils::listDirectories(__DIR__ . '/fixtures/global');
        $this->assertEquals('org', implode(',', $directories));
        $directories = FileSystemUtils::listDirectories(__DIR__ . '/fixtures/global/org');
        $this->assertEquals('example', implode(',', $directories));
    }

    public function testAllInstalledProjectsInBaseDir()
    {
        $projects = FileSystemUtils::allInstalledProjectsInBaseDir(__DIR__ . '/fixtures/global');
        $this->assertEquals('org/example', implode(',', $projects));
    }
}
