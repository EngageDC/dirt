<?php
use Dirt\Tools\GitBuilder as Git;
use Dirt\Tools\MySQLBuilder as MySQL;


class TerminalTest extends \PHPUnit_Framework_TestCase
{
  public function testGitRunner() {
    $git = new Git();

    // Create a new project with a specific name
    $this->assertEquals($git->push('origin', 'master'), 'git push origin master');
    $this->assertEquals($git->branch('staging'), 'git branch staging');
    $this->assertEquals($git->checkout('staging'), 'git checkout staging');
    $this->assertEquals($git->fetch('--all'), 'git fetch --all');
    $this->assertEquals($git->reset('--hard', 'origin/staging'), 'git reset --hard origin/staging');
    $this->assertEquals($git->merge('master'), 'git merge master');
    $this->assertEquals($git->push('origin', 'staging'), 'git push origin staging');
    $this->assertEquals($git->checkout('master'), 'git checkout master');
  }

  public function testMySQLRunner() {
    // Create a new project with a specific name

    $config = new stdClass;
    $config->username = 'username';
    $config->password = 'password';

    $mysql = new MySQL($config);

    $this->assertEquals($mysql->query('SELECT * FROM testDatabase WHERE a == b'),
                        'mysql -uusername -ppassword -e "SELECT * FROM testDatabase WHERE a == b"');
    $this->assertEquals($mysql->query('SELECT * FROM testDatabase WHERE a == b'),
                        'mysql -uusername -ppassword -e "SELECT * FROM testDatabase WHERE a == b"');
    $this->assertEquals($mysql->import("/path/to/file.sql", 'databaseName'),
                        "mysql -uusername -ppassword databaseName < /path/to/file.sql");
  }
}
