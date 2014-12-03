<?php
require_once(__DIR__ . '/CreateProjectTest.php');

class CreateLaravelProjectTest extends CreateProjectTest
{
	protected static $createCommand = 'dirt create "Integration Test Project" -f laravel4 --description "This is just a test project created for the purpose of integration testing" --skip-repository';

	public function testDatabaseConfig() {
		// local/dev
		$localConfig = require(static::$tmpFolder . '/' . static::$projectFolderName . '/app/config/local/database.php');

		$devDatabaseCredentials = static::$project->getDatabaseCredentials('dev');
		
		$this->assertEquals($devDatabaseCredentials['hostname'], $localConfig['connections']['mysql']['host']);
		$this->assertEquals($devDatabaseCredentials['database'], $localConfig['connections']['mysql']['database']);
		$this->assertEquals($devDatabaseCredentials['username'], $localConfig['connections']['mysql']['username']);
		$this->assertEquals($devDatabaseCredentials['password'], $localConfig['connections']['mysql']['password']);

		// Staging
		$stagingConfig = require(static::$tmpFolder . '/' . static::$projectFolderName . '/app/config/staging/database.php');

		$stagingDatabaseCredentials = static::$project->getDatabaseCredentials('staging');
		$this->assertEquals($stagingDatabaseCredentials['hostname'], $stagingConfig['connections']['mysql']['host']);
		$this->assertEquals($stagingDatabaseCredentials['database'], $stagingConfig['connections']['mysql']['database']);
		$this->assertEquals($stagingDatabaseCredentials['username'], $stagingConfig['connections']['mysql']['username']);
		$this->assertEquals($stagingDatabaseCredentials['password'], $stagingConfig['connections']['mysql']['password']);
	}

	public function testEnvironmentDetection() {
		$filename = static::$tmpFolder . '/' . static::$projectFolderName . '/bootstrap/start.php';

		$this->assertFileExists($filename);

		$configLines = explode("\n", file_get_contents($filename));

		$foundEnvironmentDetectionSection = false;
		foreach ($configLines as $line) {
			$line = trim($line);

			if (strlen($line) <= 0) {
				continue;
			}

			if (strpos($line, '$app->detectEnvironment(array(') !== FALSE) {
				$foundEnvironmentDetectionSection = true;
			} elseif ($foundEnvironmentDetectionSection) {
				if ($line == '));') {
					break;
				} else {
					$this->assertTrue($line == "'local' => array('Integration-Test-Project')," || $line == "'staging' => array('stage'),", 'Found local and staging section');
				}
			}
		}

		$this->assertTrue($foundEnvironmentDetectionSection, 'Found environment detection section');
	}

}
