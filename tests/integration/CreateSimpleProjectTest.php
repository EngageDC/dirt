<?php
use Symfony\Component\Process\Process;
use Dirt\Repositories\VersionControlRepositoryGitLab;
use Dirt\Repositories\VersionControlRepositoryGitHub;
use Dirt\Configuration;
use Dirt\Project;

class CreateSimpleProjectTest extends \PHPUnit_Framework_TestCase
{
	private $config;
	private $project;
	private $tmpFolder = null;
	private $projectFolderName = 'Integration-Test-Project';

	public function setUp() {
		$this->config = new Configuration();
		$this->tmpFolder = sys_get_temp_dir();

		// Output temp folder
		//echo 'Temporary folder: ' . $this->tmpFolder;

		// Use a tmp folder
		chdir($this->tmpFolder);

		// Run dirt create for a simple project (No framework specified)
		$process = new Process('dirt create "Integration Test Project" --description "This is just a test project created for the purpose of integration testing"');

		// This will throw an exception if the process couldn't be executed
		// successfully (i.e. the process exited with a non-zero code)
		$process->mustRun();

		// Load project config
		$this->project = Project::fromDirtfile($this->tmpFolder . '/' . $this->projectFolderName . '/Dirtfile.json');
	}

	public function testDirectoryContents() {
		// Root directory exists
		$this->assertTrue(file_exists($this->tmpFolder . '/' . $this->projectFolderName), 'Directory exists');
		$this->assertTrue(is_dir($this->tmpFolder . '/' . $this->projectFolderName), 'Directory is valid');

		// Files in directory
		$this->assertTrue(file_exists($this->tmpFolder . '/' . $this->projectFolderName . '/Dirtfile.json'), 'Dirtfile exists');
		$this->assertTrue(file_exists($this->tmpFolder . '/' . $this->projectFolderName . '/README.md'), 'README file exists');

		$this->assertContains($this->project->getName(false), file_get_contents($this->tmpFolder . '/' . $this->projectFolderName . '/README.md'), 'README file has project name');

		// Public folder exists
		$this->assertTrue(file_exists($this->tmpFolder . '/' . $this->projectFolderName . '/public'), 'Public folder exists');
		$this->assertTrue(is_dir($this->tmpFolder . '/' . $this->projectFolderName . '/public'), 'Public folder is valid');
	}

	public function tearDown() {
		$this->cleanUp();
	}

	protected function onNotSuccessfulTest(Exception $e) {
    	$this->cleanUp();

    	parent::onNotSuccessfulTest($e);
    }

    private function cleanUp() {
    	// Delete folder
    	if ($this->tmpFolder && $this->projectFolderName && strlen($this->tmpFolder) > 2 && strlen($this->projectFolderName) > 2) {
	    	$process = new Process('rm -rf ' . $this->tmpFolder . '/' . $this->projectFolderName);
	    	$process->start();
	    }

	    // Delete repository
    	try {
            $versionControlRepository = ($this->config->scm->type == 'gitlab') ?
                new VersionControlRepositoryGitLab($this->config) : new VersionControlRepositoryGitHub($this->config);
            $versionControlRepository->deleteRepository($this->project);
        } catch (\Exception $e) {
            // Ignore any errors, the project probably doesn't exist
        }
    }

}
