<?php
namespace Dirt\Deployer;

use Symfony\Component\Process\Process;
use Dirt\Configuration;
use Dirt\TemplateHandler;
use Dirt\Tools\LocalTerminal;
use Dirt\Tools\RemoteTerminal;
use Dirt\Tools\RemoteFileSystem;
use Dirt\Tools\GitBuilder;
use Dirt\Tools\MySQLBuilder;

class StagingDeployer extends Deployer
{
    private $databaseCredentials;
    private $stagingTerminal;

    /**
     * Returns current environment name
     * @return string
     */
    public function getEnvironment()
    {
        return 'staging';
    }

    /**
     * Invokes the deployment process, pushing latest version to the staging server
     */
    public function deploy()
    {
        $this->output->write('Deploy... ' . PHP_EOL);

        $this->databaseCredentials = $this->project->getDatabaseCredentials('staging');

        $this->synchronizeGit();
        $this->synchronizeRemoteFiles();
        $this->synchronizeRemoteDatabase();

        $this->output->writeln('');
        $this->output->writeln('Deployment finished to <comment>'. $this->project->getStagingUrl() .'</comment>');

        if (!$this->no && ($this->yes || $this->dialog->askConfirmation(
            $this->output,
            '<question>Do you want to open your webbrowser now?</question> ',
            true
        )))
        {
            $process = new Process((defined('PHP_WINDOWS_VERSION_BUILD') ? 'start' : 'open') . ' ' . $this->project->getStagingUrl());
            $process->run();
        }
    }

    /**
     * Removes all configuration, files, and database credentials from the staging server
     */
    public function undeploy()
    {
        $this->output->write('Undeploy... ' . PHP_EOL);

        $this->databaseCredentials = $this->project->getDatabaseCredentials('staging');

        // Connect to server
        $this->output->write('Connecting to staging server... ');

        $this->stagingTerminal = new RemoteTerminal($this->config->environments->staging, $this->output);

        $this->output->writeln('<info>OK</info>');

        // Remove vhost
        $this->output->write('Removing vhost... ');
        $this->stagingTerminal->ignoreError()->run('sudo rm /etc/httpd/sites-enabled/site_'. strtolower($this->project->getName()) .'.conf');
        $this->output->writeln('<info>OK</info>');

        // Remove site directory
        $this->output->write('Removing site directory... ');
        $siteDir = $this->project->getStagingUrl(false);

        if (strlen($siteDir) <= 0) { // We don't want to risk wiping the whole sites directory, do we?
          $this->output->writeln('<error>Error! Invalid site dir: '. $siteDir .'</error>');
        }
        else {
          $this->stagingTerminal->ignoreError()->run('sudo rm -rf /var/www/sites/' . $siteDir . '/');
          $this->output->writeln('<info>OK</info>');
        }

        $mysql = new MySQLBuilder($this->config->environments->staging->mysql);

        // Remove database
        $this->output->write('Removing database "' . $this->databaseCredentials['database'] . '"... ');
        $this->stagingTerminal->run($mysql->query("DROP DATABASE ". $this->databaseCredentials['database'] .";"));
        $this->output->writeln('<info>OK</info>');

        // Remove database user
        $this->output->write('Removing database user... ');
        $this->stagingTerminal->run($mysql->query("DROP USER '". $this->databaseCredentials['username'] ."'@'localhost';"));
        $this->output->writeln('<info>OK</info>');

        // Test config
        $this->output->write('Testing vhost config syntax... ');
        $response = $this->stagingTerminal->ignoreError()->run('sudo /etc/init.d/httpd configtest');

        if (strpos($response, 'No such file or directory') === False) {
          $this->output->writeln('<info>OK</info>');
        }
        else {
          $this->output->writeln('<info>File does not exist. Already removed?</info>');
        }

        // Restart apache
        $this->output->write('Restarting httpd... ');
        $this->stagingTerminal->run('sudo /etc/init.d/httpd graceful');
        $this->output->writeln('<info>OK</info>');
    }

    /**
     * Commits local changes ifneedbe and merges them to the staging branch
     */
    private function synchronizeGit()
    {
        $this->output->write('Synchronize Git... ' . PHP_EOL);

        // Verify local git repository
        $this->output->writeln('Pushing local changes (this may take a few minutes)...');

        $terminal = new LocalTerminal($this->project->getDirectory(), $this->output);
        $git = new GitBuilder();

        // Make sure we are on the master branch
        $terminal->run($git->checkout('master'));

        // Check if there is any local changes
        $status = $terminal->run($git->status());

        if (strpos($status, 'nothing to commit') === FALSE)
        {
          // Show diff
          $this->output->writeln($terminal->ignoreError()->run($git->diff()));

          $message = $this->dialog->ask(
              $this->output,
              '<question>You have uncommitted changes, please provide a commit message:</question> '
          );

          $terminal->run($git->add('-A .'));
          $terminal->run($git->commit($message));
        }

        // Push all changes on master branch
        $terminal->run($git->push('origin master'));
        // Make sure that the "staging" branch exists
        $terminal->ignoreError()->run($git->branch('staging'));
        // Check out the staging branch
        $terminal->run($git->checkout('staging'));
        // Make sure staging is up to date
        $terminal->run($git->fetch('origin'));
        // This will fail if the remote staging branch doesn't exist, so just ignore silently
        $terminal->ignoreError()->run($git->reset('--hard origin/staging'));
        // Merge changes
        $terminal->run($git->merge('master'));
        // Push staging branch
        $terminal->run($git->push('origin', 'staging'));
        // Go back to master branch
        $terminal->run($git->checkout('master'));
    }

    /**
     * Syncs newest files to the staging server
     */
    private function synchronizeRemoteFiles()
    {
        $this->output->write('Synchronize Remote Files... ' . PHP_EOL);

        // Connect to server
        $this->output->write('Connecting to staging server... ');

        $this->stagingTerminal = new RemoteTerminal($this->config->environments->staging, $this->output);

        $this->output->writeln('<info>OK</info>');

        // Verify initial setup
        // TODO: We should make the vhost path configurable and use sites-available with symlinking
        $this->output->write('Checking if site has been configured... ');
        $response = $this->stagingTerminal->ignoreError()->run('cat /etc/httpd/sites-enabled/site_'. strtolower($this->project->getName()) .'.conf');

        if (strpos($response, 'No such file or directory') !== FALSE) {
            $this->output->writeln('<comment>Nope</comment>');
            $this->configureStagingServer();
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Pull
        $this->output->writeln('Pulling changes...');
        $git = new GitBuilder();

        $response = $this->stagingTerminal
            ->ignoreError()
            ->add('cd /var/www/sites/' . $this->project->getStagingUrl(false))
            ->add($git->fetch('--all'))
            ->add($git->reset('--hard origin/master'))
            ->execute();

        if (strpos($response, 'Not a git repository') !== FALSE) {
          $this->setupGitRepository();
        }

        // Configure framework if needed
        if ($this->project->getFramework() !== FALSE) {
          $this->configureFramework();
        }

        // Update file permissions
        $this->output->write('Updating file permissions... ');

        $this->allowWebServerAccessToSiteFiles();

        $this->output->writeln('<info>OK</info>');
    }

    private function setupGitRepository() {
      $this->output->write('Setting up repository... ');

      $git = new GitBuilder();

      $this->stagingTerminal
        ->ignoreError()
        ->add('cd /var/www/sites/' . $this->project->getStagingUrl(false))
        ->add('rm -rf public/')
        ->add($git->clone($this->project->getRepositoryUrl() . ' .'))
        ->add($git->checkout('staging'))
        ->execute();

      $this->output->writeln('<info>OK</info>');
    }

    private function configureFramework() {
      $this->output->write('Configuring '. $this->project->getFramework()->getName() .'... ');

      try {
        $this->project->getFramework()->configureEnvironment('staging',
         $this->project,
         $this->stagingTerminal->getSSHConnection()
        );

        $this->output->writeln('<info>OK</info>');
      }
      catch (\Exception $e) {
        $this->output->writeln('<error>Error: '. $e->getMessage() .'</error>');
        throw new \RuntimeException('Error: '. $e->getMessage());
      }
    }

    private function allowWebServerAccessToSiteFiles() {
      $this->stagingTerminal
        ->add('cd /var/www/sites/' . $this->project->getStagingUrl(false))
        ->add('sudo chgrp -R webdata .')
        ->add('sudo chmod -R g+w .')
        ->execute();
    }

    /**
     * Creates vhost configuration and directories on staging server
     */
    private function configureStagingServer()
    {
        $this->output->write('Configure Staging Server... ' . PHP_EOL);

        // Create vhost file
        $this->output->write("\t" . 'Creating vhost... ');
        $variables = array(
            '__PROJECT_NAME__' => strtolower($this->project->getName()),
            '__STAGING_URL__' => $this->project->getStagingUrl(false)
        );

        $templateHandler = new TemplateHandler();
        $templateHandler->setProject($this->project);
        $vhostTemplate = $templateHandler->generateTemplate('staging_vhost.conf');

        $this->stagingTerminal->run('sudo sh -c \'echo "'. $vhostTemplate .'" > /etc/httpd/sites-enabled/site_'. strtolower($this->project->getName()) .'.conf\'');
        $this->output->writeln('<info>OK</info>');

        // Create directory
        $this->output->write("\t" . 'Creating directory... ');
        $this->stagingTerminal->run('sudo mkdir -p /var/www/sites/' . $this->project->getStagingUrl(false) . '/public');
        $this->output->writeln('<info>OK</info>');

        // Create directory
        $this->output->write("\t" . 'Setting directory permissions... ');
        $this->stagingTerminal->run('sudo chown -R '. $this->config->environments->staging->username .':'. $this->config->environments->staging->username .' /var/www/sites/' . $this->project->getStagingUrl(false));
        $this->output->writeln('<info>OK</info>');

        // Test config
        $this->output->write("\t" . 'Testing vhost config syntax... ');
        $this->stagingTerminal->run('sudo /etc/init.d/httpd configtest');
        $this->output->writeln('<info>OK</info>');

        // Restart apache
        $this->output->write("\t" . 'Restarting httpd... ');
        $this->stagingTerminal->run('sudo /etc/init.d/httpd graceful');
        $this->output->writeln('<info>OK</info>');
    }

    /**
     * Verifies that database has been set up on remote server.
     * If not, the configuration and import process is invoked
     */
    private function synchronizeRemoteDatabase()
    {
        $this->output->write('Synchronize Remote Database... ' . PHP_EOL);
        // Verify that database is set up
        $this->output->write('Checking if database has been configured... ');

        $mysql = new MySQLBuilder($this->config->environments->staging->mysql);

        $response = $this->stagingTerminal->ignoreError()->run(
            $mysql->query("SHOW DATABASES LIKE '". $this->databaseCredentials['database'] ."'")
            . "| grep ". $this->databaseCredentials['database']);

        if (strlen($response) == 0) {
            $this->output->writeln('<comment>Nope</comment>');

            // Configure database
            $this->configureStagingDatabase();

            // Remind user to import the dev database
            $this->output->writeln('If you want to transfer your database to staging, just run <comment>dirt transfer:db development staging</comment>.');
        } else {
            $this->output->writeln('<info>OK</info>');
        }
    }

    /**
     * Creates database and database user account on staging server
     */
    private function configureStagingDatabase()
    {
      $this->output->write("\t" . 'Configuring database account on staging server... ');

      $mysql = new MySQLBuilder($this->config->environments->staging->mysql);

      // Create database
      $this->stagingTerminal->run($mysql->query("CREATE DATABASE " . $this->databaseCredentials['database'] . ";"));

      // Create user
      $this->stagingTerminal->run($mysql->query("GRANT ALL ON ". $this->databaseCredentials['database'] .".* TO '"
        . $this->databaseCredentials['username'] ."'@'localhost' IDENTIFIED BY '"
        . $this->databaseCredentials['password'] ."';"));

      $this->output->writeln('<info>OK</info>');
    }

}
