<?php
use Symfony\Component\Process\Process;
use Dirt\Repositories\VersionControlRepositoryGitLab;
use Dirt\Repositories\VersionControlRepositoryGitHub;
use Dirt\Configuration;
use Dirt\Project;

class DeployStagingTest extends \PHPUnit_Framework_TestCase
{
  protected static $config;
  protected static $project;
  protected static $versionControlRepository;
  protected static $tmpFolder = null;
  protected static $projectFolderName = 'Integration-Test-Project';
  protected static $createCommand = 'dirt create "Integration Test Project" --description "This is just a test project created for the purpose of integration testing" && cd Integration-Test-Project';

  public static function setUpBeforeClass() {
    static::$config = new Configuration();
    static::$versionControlRepository = (static::$config->scm->type == 'gitlab') ?
                new VersionControlRepositoryGitLab(static::$config) : new VersionControlRepositoryGitHub(static::$config);
    static::$tmpFolder = sys_get_temp_dir();

    // Output temp folder
    fwrite(STDERR, 'Temporary folder: ' . static::$tmpFolder . PHP_EOL);

    // Use a tmp folder
    chdir(static::$tmpFolder);

    // Run dirt create for a simple project (No framework specified)
    $process = new Process(static::$createCommand);

    // This will throw an exception if the process couldn't be executed
    // successfully (i.e. the process exited with a non-zero code)
    $process->mustRun();
    fwrite(STDERR, $process->getOutput() . PHP_EOL);

    // Load project config
    static::$project = Project::fromDirtfile(static::$tmpFolder . '/' . static::$projectFolderName . '/Dirtfile.json');

    // Change to project directory
    chdir(static::$tmpFolder . '/' . static::$projectFolderName);
  }

  public function testProjectConfig() {
    $this->assertNotNull(static::$project, 'Project instance valid');
  }

  public function testDeployChange() {
    // Make changes
    $contents = sha1(time());
    file_put_contents(static::$tmpFolder . '/' . static::$projectFolderName . '/public/index.html', $contents);

    // Commit changes and then deploy
    $commands = [
      'git add public/index.html',
      'git commit -m \'Updated index\'',
      'dirt deploy staging --no'
    ];

    foreach ($commands as $command) {
      $process = new Process($command);
        fwrite(STDERR, $command . PHP_EOL);
          $process->mustRun();
          fwrite(STDERR, $process->getOutput() . PHP_EOL);
    }
    exit();

    // Verify that contents exists and matches
    $remoteContents = file_get_contents(static::$project->getStagingUrl());
    $this->assertEquals($contents, $remoteContents);
  }

  public static function tearDownAfterClass() {
    static::cleanUp();
  }

  protected function onNotSuccessfulTest(Exception $e) {
    static::cleanUp();

    parent::onNotSuccessfulTest($e);
  }

  private static function cleanUp() {
    return;    // Run dirt undeploy
    $process = new Process('dirt deploy staging --undeploy --yes');
    $process->mustRun();

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
