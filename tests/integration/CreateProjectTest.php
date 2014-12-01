<?php
use Symfony\Component\Process\Process;
use Dirt\Repositories\VersionControlRepositoryGitLab;
use Dirt\Repositories\VersionControlRepositoryGitHub;
use Dirt\Configuration;
use Dirt\Project;

class CreateProjectTest extends \PHPUnit_Framework_TestCase
{
	protected static $config;
	protected static $project;
	protected static $versionControlRepository;
	protected static $tmpFolder = null;
	protected static $projectFolderName = 'Integration-Test-Project';
	protected static $createCommand = 'dirt create "Integration Test Project" --description "This is just a test project created for the purpose of integration testing"';

	public static function setUpBeforeClass() {
		static::$config = new Configuration();
		static::$versionControlRepository = (static::$config->scm->type == 'gitlab') ?
                new VersionControlRepositoryGitLab(static::$config) : new VersionControlRepositoryGitHub(static::$config);
		static::$tmpFolder = sys_get_temp_dir();

		// Output temp folder
		//fwrite(STDERR, 'Temporary folder: ' . static::$tmpFolder . PHP_EOL);

		// Use a tmp folder
		chdir(static::$tmpFolder);

		// Run dirt create for a simple project (No framework specified)
		$process = new Process(static::$createCommand);

		// This will throw an exception if the process couldn't be executed
		// successfully (i.e. the process exited with a non-zero code)
		$process->mustRun();

		// Load project config
		static::$project = Project::fromDirtfile(static::$tmpFolder . '/' . static::$projectFolderName . '/Dirtfile.json');
	}

	public function testProjectConfig() {
		$this->assertNotNull(static::$project, 'Project instance valid');
	}

	public function testDirectoryConsistency() {
		// Root directory exists
		$this->assertTrue(file_exists(static::$tmpFolder . '/' . static::$projectFolderName), 'Directory exists');
		$this->assertTrue(is_dir(static::$tmpFolder . '/' . static::$projectFolderName), 'Directory is valid');

		// Files in directory
		$this->assertTrue(file_exists(static::$tmpFolder . '/' . static::$projectFolderName . '/Dirtfile.json'), 'Dirtfile exists');
		$this->assertTrue(file_exists(static::$tmpFolder . '/' . static::$projectFolderName . '/README.md'), 'README file exists');

		$this->assertContains(static::$project->getName(false), file_get_contents(static::$tmpFolder . '/' . static::$projectFolderName . '/README.md'), 'README file has project name');

		// Public folder exists
		$this->assertTrue(file_exists(static::$tmpFolder . '/' . static::$projectFolderName . '/public'), 'Public folder exists');
		$this->assertTrue(is_dir(static::$tmpFolder . '/' . static::$projectFolderName . '/public'), 'Public folder is valid');
	}

	public function testRemoteRepository() {
		$this->assertTrue(static::$versionControlRepository->exists(static::$project), 'Remote git repository exists');
	}

	public static function tearDownAfterClass() {
		static::cleanUp();
	}

	protected function onNotSuccessfulTest(Exception $e) {
    	static::cleanUp();

    	parent::onNotSuccessfulTest($e);
    }

    private static function cleanUp() {
    	// Delete folder
    	if (static::$tmpFolder && static::$projectFolderName && strlen(static::$tmpFolder) > 2 && strlen(static::$projectFolderName) > 2) {
	    	$process = new Process('rm -rf ' . static::$tmpFolder . '/' . static::$projectFolderName);
	    	$process->start();
	    }

	    // Delete repository
    	try {
    		static::$versionControlRepository->delete(static::$project);
        } catch (\Exception $e) {
            // Ignore any errors, the project probably doesn't exist
        }

        // Wait a bit before running next test
        sleep(2);
    }

}
