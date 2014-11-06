<?php
use Dirt\Project;

class ProjectTest extends \PHPUnit_Framework_TestCase
{
    public function testName() {
        // Create a new project with a specific name
        $project = new Project;
        $project->setName('New Test Project');

        // Ensure that the simple name is generated correctly
        $this->assertEquals('New-Test-Project', $project->getName(true));
        $this->assertEquals('New Test Project', $project->getName(false));
    }

    public function testGitConfigParser() {
        // Create a new project and set the project directory
        // to be the root of the dirt directory
        $project = new Project;
        $project->setDirectory(__DIR__ . '/../../');

        // Parse the git config
        $project->parseGitConfig();

        // Ensure that the git config was parsed correctly
        $this->assertTrue(
            $project->getRepositoryUrl() == 'git://github.com/EngageDC/dirt.git' ||
            $project->getRepositoryUrl() == 'git@github.com:EngageDC/dirt.git'
        );
    }
}
