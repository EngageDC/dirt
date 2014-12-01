<?php
require_once(__DIR__ . '/CreateProjectTest.php');

class CreateLaravelProjectTest extends CreateProjectTest
{
	protected static $createCommand = 'dirt create "Integration Test Project" -f laravel4 --description "This is just a test project created for the purpose of integration testing"';

	public function testLaravelConfig() {
		// TODO: Verify that database config has been set up
		//var_dump(file_get_contents(static::$tmpFolder . '/' . static::$projectFolderName . '/app/config/database.php'));
	}

}
