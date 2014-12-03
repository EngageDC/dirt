<?php
namespace Dirt\Deployer;

use Symfony\Component\Process\Process;
use Dirt\Configuration;

class ProductionDeployer extends Deployer
{
    private $stagingSSH;
    private $productionSSH;
    private $stagingSFTP;
    private $productionSFTP;
    private $databaseCredentials;

    /**
     * Returns current environment name
     * @return string
     */
    public function getEnvironment()
    {
        return 'production';
    }

    /**
     * Invokes the deployment process, pushing latest version to the staging server
     */
    public function deploy()
    {
        $this->compressStagingSite();

        $this->output->writeln('');
        $this->output->writeln('Deployment finished to <comment>'. $this->project->getProductionUrl() .'</comment>');
        if (!$this->no && ($this->yes || $this->dialog->askConfirmation(
            $this->output,
            '<question>Do you want to open your webbrowser now?</question> ',
            true
        )))
        {
            $process = new Process((defined('PHP_WINDOWS_VERSION_BUILD') ? 'start' : 'open') . ' ' . $this->project->getProductionUrl());
            $process->run();
        }
    }

    /**
     * Removes all configuration, files and database credentials from the production server
     */
    public function undeploy()
    {
        $this->output->writeln('This feature is not available for production deployment');
    }

    /**
     * Creates a compressed archive from the staging server
     */
    private function compressStagingSite()
    {
        // Connect to staging server
        $this->output->write('Connecting to staging server... ');

        $this->stagingSSH = new \Net_SSH2($this->config->environments->staging->hostname, $this->config->environments->staging->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->config->environments->staging->keyfile));
        if (!$this->stagingSSH->login($this->config->environments->staging->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        }
        $this->output->writeln('<info>OK</info>');

        // Compress
        $archiveName = 'deploy_'. sha1($this->project->getName() . time()) .'.tar.gz';

        $this->output->write('Packing code for deployment... ');
        $response = $this->stagingSSH->exec('cd /var/www/sites/' . $this->project->getStagingUrl(false) . ' && tar -zcvf /tmp/'. $archiveName .' . --exclude=".git" --exclude="mixture"');
        $this->output->writeln('<info>OK</info>');
        
        // Download source code from staging
        $this->output->write('Downloading source code from staging... ');

        $this->stagingSFTP = new \Net_SFTP($this->config->environments->staging->hostname, $this->config->environments->staging->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->config->environments->staging->keyfile));
        if (!$this->stagingSFTP->login($this->config->environments->staging->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        } else {
            if (!$this->stagingSFTP->get('/tmp/' . $archiveName, $this->project->getDirectory() . '/' . $archiveName)) {
                $this->output->writeln('<error>Error: Could not download file</error>');
                exit(1);
            } else {
                $this->output->writeln('<info>OK</info>');
            }
        }

        // Removing tmp file from staging
        $this->output->write('Removing tmp archive from staging... ');
        $response = $this->stagingSSH->exec('rm /tmp/'. $archiveName);
        
        if (strlen($response) != 0) {
            $this->output->writeln('<error>Error! Unexpected response '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Uploading source code to production
        $this->output->write('Uploading source code to production... ');

        $this->productionSFTP = new \Net_SFTP($this->config->environments->production->hostname, $this->config->environments->production->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->config->environments->production->keyfile));
        if (!$this->productionSFTP->login($this->config->environments->production->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        } else {
            if (!$this->productionSFTP->put($this->project->getProductionDirectory() . '/' . $archiveName, $this->project->getDirectory() . '/' . $archiveName, NET_SFTP_LOCAL_FILE)) {
                $this->output->writeln('<error>Error: Could not upload file</error>');
                exit(1);
            } else {
                $this->output->writeln('<info>OK</info>');
            }
        }

        // Connect to production server
        $this->output->write('Connecting to production server... ');

        $this->productionSSH = new \Net_SSH2($this->config->environments->production->hostname, $this->config->environments->production->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($this->config->environments->production->keyfile));
        if (!$this->productionSSH->login($this->config->environments->production->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        }
        $this->output->writeln('<info>OK</info>');

        // Extract archive
        $this->output->write('Extracting archive... ');

        $response = $this->productionSSH->exec('cd ' . $this->project->getProductionDirectory() . '/ && tar -zxvf ' . $archiveName);
        $this->output->writeln('<info>OK</info>');

        // Configure framework if needed
        if ($this->project->getFramework() !== FALSE) {
            $this->output->write('Configuring '. $this->project->getFramework()->getName() .'... ');

            try {
                $this->project->getFramework()->configureEnvironment('production', $this->project, $this->productionSSH);
                $this->output->writeln('<info>OK</info>');
            } catch (\Exception $e) {
                $this->output->writeln('<error>Error: '. $e->getMessage() .'</error>');
                exit(1);
            }
        }

        // Ensure group write permissions
        $this->output->write('Applying group write permissions... ');

        $response = $this->productionSSH->exec('cd ' . $this->project->getProductionDirectory() . '/ && chmod -R g+w .');
        $this->output->writeln('<info>OK</info>');
        
        // Remove archive from production
        $this->output->write('Removing deploy archive from production... ');

        $response = $this->productionSSH->exec('rm ' . $this->project->getProductionDirectory() . '/' . $archiveName);

        if (strlen($response) != 0) {
            $this->output->writeln('<error>Error! Unexpected response '. trim($response) .'</error>');
            exit(1);
        } else {
            $this->output->writeln('<info>OK</info>');
        }

        // Delete local tmp deploy archive
        @unlink($this->project->getDirectory() . '/' . $archiveName);
    }
}