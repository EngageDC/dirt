<?php
use Dirt\Project;
use \Mockery as m;

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

    public function testVagrantConfigParser() {
        $project = m::mock('Dirt\Project[getSSHConfig]')
            ->shouldReceive('getSSHConfig')
            ->andReturn(file_get_contents(__DIR__ . '/../stubs/sshconfig1.txt'))
            ->mock();

        $server = $project->getDevelopmentServer();
        $this->assertEquals('127.0.0.1', $server->hostname);
        $this->assertEquals('2222', $server->port);
        $this->assertEquals('/Users/someuser/.vagrant.d/insecure_private_key', $server->keyfile);
        $this->assertEquals('vagrant', $server->username);
    }

    public function testVagrantConfigSpacesParser() {
        $project = m::mock('Dirt\Project[getSSHConfig]')
            ->shouldReceive('getSSHConfig')
            ->andReturn(file_get_contents(__DIR__ . '/../stubs/sshconfig2.txt'))
            ->mock();

        $server = $project->getDevelopmentServer();
        $this->assertEquals('127.0.0.1', $server->hostname);
        $this->assertEquals('2222', $server->port);
        $this->assertEquals('/Users/someuser/Engage/Some Site/.vagrant/machines/Some Site/virtualbox/private_key', $server->keyfile);
        $this->assertEquals('vagrant', $server->username);
    }

}
