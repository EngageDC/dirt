<?php
namespace Dirt\Deployer;

use Symfony\Component\Process\Process;
use Dirt\Configuration;
use Dirt\TemplateHandler;

class StagingDeployer extends Deployer
{
    private $ssh;
    private $databaseCredentials;

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
        $this->databaseCredentials = $this->project->getDatabaseCredentials('staging');

        $this->synchronizeGit();
        $this->synchronizeRemoteFiles();
        $this->synchronizeRemoteDatabase();

        $this->output->writeln('');
        $this->output->writeln('Deployment finished to <comment>'. $this->project->getStagingUrl() .'</comment>');
        if ($this->yes || $this->dialog->askConfirmation(
            $this->output,
            '<question>Do you want to open your webbrowser now?</question> ',
            true
        ))
        {
            $process = new Process((defined('PHP_WINDOWS_VERSION_BUILD') ? 'start' : 'open') . ' ' . $this->project->getStagingUrl());
            $process->run();
        }
    }

    /**
     * Removes all configuration, files and database credentials from the staging server
     */
    public function undeploy()
    {
        $this->databaseCredentials = $this->project->getDatabaseCredentials('staging');

        // Connect to server
        $this->output->write('Connecting to staging server... ');

        $this->ssh = new \Net_SSH2($this->config->environments->staging->hostname, $this->config->environments->staging->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->config->environments->staging->keyfile));
        if (!$this->ssh->login($this->config->environments->staging->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        }
        $this->output->writeln('<info>OK</info>');

        // Remove vhost
        $this->output->write('Removing vhost... ');
        $response = $this->ssh->exec('sudo rm /etc/httpd/sites-enabled/site_'. strtolower($this->project->getName()) .'.conf');
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<comment>Warning! Unexpected response: '. trim($response) .'</comment>');
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Remove site directory
        $this->output->write('Removing site directory... ');
        $siteDir = $this->project->getStagingUrl(false);

        if (strlen($siteDir) <= 0) { // We don't want to risk wiping the whole sites directory, do we?
            $this->output->writeln('<error>Error! Invalid site dir: '. $siteDir .'</error>');
        }

        $response = $this->ssh->exec('sudo rm -rf /var/www/sites/' . $siteDir . '/');
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<comment>Warning! Unexpected response: '. trim($response) .'</comment>');
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Remove database
        $this->output->write('Removing database... ');
        $response = $this->ssh->exec("mysql -u". $this->config->environments->staging->mysql->username ." -p". $this->config->environments->staging->mysql->password ." -e \"DROP DATABASE ". $this->databaseCredentials['database'] .";\"");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<comment>Warning! Unexpected response: '. trim($response) .'</comment>');
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Remove database user
        $this->output->write('Removing database user... ');
        $response = $this->ssh->exec("mysql -u". $this->config->environments->staging->mysql->username ." -p". $this->config->environments->staging->mysql->password ." -e \"DROP USER '". $this->databaseCredentials['username'] ."'@'localhost';\"");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<comment>Warning! Unexpected response: '. trim($response) .'</comment>');
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Test config
        $this->output->write('Testing vhost config syntax... ');
        $response = $this->ssh->exec('sudo /etc/init.d/httpd configtest');
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Restart apache
        $this->output->write('Restarting httpd... ');
        $response = $this->ssh->exec('sudo /etc/init.d/httpd graceful');
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }
    }

    /**
     * Commits local changes ifneedbe and merges them to the staging branch
     */
    private function synchronizeGit()
    {
        // Verify local git repository
        $this->output->writeln('Pushing local changes (this may take a few minutes)...');

        $process = new Process(null, $this->project->getDirectory());
        $process->setTimeout(3600);

        // Make sure we are on the master branch
        $process->setCommandLine('git checkout master');
        $process->run();
        if ($this->verbose) $this->output->write($process->getOutput());
        if (!$process->isSuccessful()) {
            $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
            exit(1);
        }

        // Check if there is any local changes
        $process->setCommandLine('git status');
        $process->run();
        if (!$process->isSuccessful()) {
            $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
            exit(1);
        }

        if (strpos($process->getOutput(), 'nothing to commit') === FALSE)
        {
            // Show diff
            $process->setCommandLine('git diff');
            $process->run();
            if ($process->isSuccessful()) {
                $this->output->writeln($process->getOutput());
            }

            $message = $this->dialog->ask(
                $this->output,
                '<question>You have uncommitted changes, please provide a commit message:</question> '
            );

            $process->setCommandLine('git add -A .');
            $process->run();
            if (!$process->isSuccessful()) {
                $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
                exit(1);
            }

            $process->setCommandLine('git commit -am ' . escapeshellarg($message));
            $process->run();
            if ($this->verbose) $this->output->write($process->getOutput());
            if (!$process->isSuccessful()) {
                $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
                exit(1);
            }
        }

        // Push all changes on master branch
        $process->setCommandLine('git push origin master');
        $process->run();
        if ($this->verbose) $this->output->write($process->getOutput());
        if (!$process->isSuccessful()) {
            $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
            exit(1);
        }

        // Make sure that the "staging" branch exists
        $process->setCommandLine('git branch staging');
        $process->run();

        // Check out the staging branch
        $process->setCommandLine('git checkout staging');
        if ($this->verbose) $this->output->write($process->getOutput());
        $process->run();
        if (!$process->isSuccessful()) {
            $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
            exit(1);
        }

        // Make sure staging is up to date
        $process->setCommandLine('git fetch --all');
        if ($this->verbose) $this->output->write($process->getOutput());
        $process->run();
        if (!$process->isSuccessful()) {
            $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
            exit(1);
        }

        // This will fail if the remote staging branch doesn't exist, so just ignore silently
        $process->setCommandLine('git reset --hard origin/staging');
        $process->run();

        // Merge changes
        $process->setCommandLine('git merge master');
        if ($this->verbose) $this->output->write($process->getOutput());
        $process->run();
        if (!$process->isSuccessful()) {
            $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
            exit(1);
        }

        // Push staging branch
        $process->setCommandLine('git push origin staging');
        if ($this->verbose) $this->output->write($process->getOutput());
        $process->run();
        if (!$process->isSuccessful()) {
            $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
            exit(1);
        }

        // Go back to master branch
        $process->setCommandLine('git checkout master');
        if ($this->verbose) $this->output->write($process->getOutput());
        $process->run();
        if (!$process->isSuccessful()) {
            $this->output->writeln('<error>Error: Could not run "'. $process->getCommandLine() .'", git returned: ' . trim($process->getErrorOutput()) . '</error>');
            exit(1);
        }
    }

    /**
     * Syncs newest files to the staging server
     */
    private function synchronizeRemoteFiles()
    {
        // Connect to server
        $this->output->write('Connecting to staging server... ');

        $this->ssh = new \Net_SSH2($this->config->environments->staging->hostname, $this->config->environments->staging->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->config->environments->staging->keyfile));
        if (!$this->ssh->login($this->config->environments->staging->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        }
        $this->output->writeln('<info>OK</info>');

        // Verify initial setup
        $this->output->write('Checking if site has been configured... ');
        $response = $this->ssh->exec('cat /etc/httpd/sites-enabled/site_'. strtolower($this->project->getName()) .'.conf');
        if (strpos($response, 'No such file or directory') !== FALSE) {
            $this->output->writeln('<comment>Nope</comment>');
            $this->configureStagingServer();
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Pull
        $this->output->writeln('Pulling changes...');
        $response = $this->ssh->exec('cd /var/www/sites/' . $this->project->getStagingUrl(false) . ' && git fetch --all && git reset --hard origin/master');
        
        if (strpos($response, 'Not a git repository') !== FALSE) {
            $response = $this->ssh->exec('cd /var/www/sites/' . $this->project->getStagingUrl(false) . ' && rm -rf public/ && git clone '. $this->project->getRepositoryUrl() .' . && git checkout staging');
        }

        $this->output->writeln($response);

        // Configure framework if needed
        if ($this->project->getFramework() !== FALSE) {
            $this->output->write('Configuring '. $this->project->getFramework()->getName() .'... ');

            try {
                $this->project->getFramework()->configureEnvironment('staging', $this->project, $this->ssh);
                $this->output->writeln('<info>OK</info>');
            } catch (\Exception $e) {
                $this->output->writeln('<error>Error: '. $e->getMessage() .'</error>');
                exit(1);
            }
        }

        // Update file permissions
        $this->output->write('Updating file permissions ');

        // Check if we have sudo access by running simple command via sudo
        $response = $this->ssh->exec('cd /var/www/sites/' . $this->project->getStagingUrl(false) . ' && sudo chgrp -R webdata . && sudo chmod -R g+w .');

        // No sudo access?
        if (strpos($response, 'incorrect password attempts') !== FALSE) {
            $this->output->write('without sudo...');
            $response = $this->ssh->exec('cd /var/www/sites/' . $this->project->getStagingUrl(false) . ' && chgrp -R webdata . && chmod -R g+w .');
        } else {
            $this->output->write('via sudo...');
            $response = $this->ssh->exec('cd /var/www/sites/' . $this->project->getStagingUrl(false) . ' && sudo chgrp -R webdata . && sudo chmod -R g+w .');
        }

        $this->output->writeln('<info>OK</info>');
    }

    /**
     * Creates vhost configuration and directories on staging server
     */
    private function configureStagingServer()
    {
        // Create vhost file
        $this->output->write("\t" . 'Creating vhost... ');
        $variables = array(
            '__PROJECT_NAME__' => strtolower($this->project->getName()),
            '__STAGING_URL__' => $this->project->getStagingUrl(false)
        );

        $templateHandler = new TemplateHandler();
        $templateHandler->setProject($this->project);
        $vhostTemplate = $templateHandler->generateTemplate('staging_vhost.conf');

        $response = $this->ssh->exec('sudo sh -c \'echo "'. $vhostTemplate .'" > /etc/httpd/sites-enabled/site_'. strtolower($this->project->getName()) .'.conf\'');
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Create directory
        $this->output->write("\t" . 'Creating directory... ');
        $response = $this->ssh->exec('sudo mkdir -p /var/www/sites/' . $this->project->getStagingUrl(false) . '/public');
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Create directory
        $this->output->write("\t" . 'Setting directory permissions... ');
        $response = $this->ssh->exec('sudo chown -R '. $this->config->environments->staging->username .':'. $this->config->environments->staging->username .' /var/www/sites/' . $this->project->getStagingUrl(false));
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Test config
        $this->output->write("\t" . 'Testing vhost config syntax... ');
        $response = $this->ssh->exec('sudo /etc/init.d/httpd configtest');
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Restart apache
        $this->output->write("\t" . 'Restarting httpd... ');
        $response = $this->ssh->exec('sudo /etc/init.d/httpd graceful');
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }
    }

    /**
     * Verifies that database has been set up on remote server.
     * If not, the configuration and import process is invoked
     */
    private function synchronizeRemoteDatabase()
    {
        // Verify that database is set up
        $this->output->write('Checking if database has been configured... ');
        $response = $this->ssh->exec("mysql -u". $this->config->environments->staging->mysql->username ." -p". $this->config->environments->staging->mysql->password ." -e \"SHOW DATABASES LIKE '". $this->databaseCredentials['database'] ."'\" | grep ". $this->databaseCredentials['database']);
        if (strlen($response) == 0) {
            $this->output->writeln('<comment>Nope</comment>');

            // Configure database
            $this->configureStagingDatabase();

            // Import the dev database
            $this->importDevDatabase();
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

        // Create database
        $response = $this->ssh->exec("mysql -u". $this->config->environments->staging->mysql->username ." -p". $this->config->environments->staging->mysql->password ." -e \"CREATE DATABASE ". $this->databaseCredentials['database'] .";\"");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }

        // Create user
        $response = $this->ssh->exec("mysql -u". $this->config->environments->staging->mysql->username ." -p". $this->config->environments->staging->mysql->password ." -e \"GRANT ALL ON ". $this->databaseCredentials['database'] .".* TO '". $this->databaseCredentials['username'] ."'@'localhost' IDENTIFIED BY '". $this->databaseCredentials['password'] ."';\"");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }

        $this->output->writeln('<info>OK</info>');
    }

    private function importDevDatabase()
    {
        // Ask for confirmation first
        $this->output->writeln('');
        if (!$this->yes) {
            if (!$this->dialog->askConfirmation(
                $this->output,
                '<question>The staging database is empty, do you want to copy the dev database to staging?</question> ',
                true
            ))
            {
                return;
            }
        }

        // Dump dev database
        $devDeployer = new DevDeployer();
        $devDeployer->setInput($this->input);
        $devDeployer->setOutput($this->output);
        $devDeployer->setDialog($this->dialog);
        $devDeployer->setProject($this->project);
        $devDeployer->setConfig($this->config);
        $devDeployer->dumpDatabase();

        // Create file hash to avoid collisions
        $fileHash = sha1($this->project->getName() . time());

        // Upload MySQL dump
        $this->output->write("\t" . 'Uploading database dump... ');
        $sftp = new \Net_SFTP($this->config->environments->staging->hostname, $this->config->environments->staging->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->config->environments->staging->keyfile));
        if (!$sftp->login($this->config->environments->staging->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        } else {
            $sftp->put('/tmp/dev_'. $fileHash .'_structure.sql', $this->project->getDirectory() . '/db/dev_structure.sql', NET_SFTP_LOCAL_FILE);
            $sftp->put('/tmp/dev_'. $fileHash .'_content.sql', $this->project->getDirectory() . '/db/dev_content.sql', NET_SFTP_LOCAL_FILE);

            $this->output->writeln('<info>OK</info>');
        }

        // Migrate MySQL dump
        $this->output->write("\t" . 'Migrating database dump... ');        
        $response = $this->ssh->exec("sed -i 's/". $this->project->getDevUrl(false) ."/". $this->project->getStagingUrl(false) ."/g' /tmp/dev_". $fileHash ."_content.sql");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Import MySQL dump
        $this->output->write("\t" . 'Importing database dump... ');
        $response = $this->ssh->exec("mysql -u". $this->databaseCredentials['username'] ." -p". $this->databaseCredentials['password'] ." ". $this->databaseCredentials['database'] ." < /tmp/dev_". $fileHash ."_structure.sql");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }
        
        $response = $this->ssh->exec("mysql -u". $this->databaseCredentials['username'] ." -p". $this->databaseCredentials['password'] ." ". $this->databaseCredentials['database'] ." < /tmp/dev_". $fileHash ."_content.sql");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }

        $this->output->writeln('<info>OK</info>');
        

        // Clean up
        $this->output->write("\t" . 'Cleaning up... ');

        $response = $this->ssh->exec("rm /tmp/dev_". $fileHash ."_structure.sql");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }

        $response = $this->ssh->exec("rm /tmp/dev_". $fileHash ."_content.sql");
        if ($this->ssh->getExitStatus() != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }

        $this->output->writeln('<info>OK</info>');
    }
}