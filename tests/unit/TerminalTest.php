<?php
use Dirt\Tools\GitRunner;
use Dirt\Tools\MySQLRunner;
use Dirt\Tools\TerminalInterface;


class TerminalTest extends \PHPUnit_Framework_TestCase
{
  public function testGitRunner() {
    // Create a new project with a specific name
    $git = new GitRunner(new DummyTerminal());

    $this->assertEquals($git->push('origin', 'master'), 'git push origin master');
    $this->assertEquals($git->branch('staging'), 'git branch staging');
    $this->assertEquals($git->checkout('staging'), 'git checkout staging');
    $this->assertEquals($git->fetch('--all'), 'git fetch --all');
    $this->assertEquals($git->ignoreError()->reset('--hard', 'origin/staging'), 'git reset --hard origin/staging --ignore_error');
    $this->assertEquals($git->merge('master'), 'git merge master');
    $this->assertEquals($git->push('origin', 'staging'), 'git push origin staging');
    $this->assertEquals($git->checkout('master'), 'git checkout master');
  }

  public function testMySQLRunner() {
    // Create a new project with a specific name
    $mysql = new MySQLRunner(new DummyTerminal(), 'username', 'password');
    $this->assertEquals($mysql->query('SELECT * FROM testDatabase WHERE a == b'),
                        'mysql -uusername -ppassword -e "SELECT * FROM testDatabase WHERE a == b"');
    $this->assertEquals($mysql->ignoreError()->query('SELECT * FROM testDatabase WHERE a == b'),
                        'mysql -uusername -ppassword -e "SELECT * FROM testDatabase WHERE a == b" --ignore_error');

  }
}

class DummyTerminal implements TerminalInterface {

  private $ignoreError = false;

  public function run($command) {
    if($this->ignoreError) {
      $command .= ' --ignore_error';
      $this->ignoreError = false;
    }

    return $command;
  }

  public function ignoreError() {
    $this->ignoreError = true;
    return $this;
  }
}
