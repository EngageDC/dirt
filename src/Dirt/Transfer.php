<?php
namespace Dirt;

use Dirt\Configuration;
use Dirt\Tools\RemoteTerminal;
use Dirt\Tools\RemoteFileSystem;

class Transfer
{
    private $project;
    private $environment;
    private $output;

    public function setProject($project) {
        $this->project = $project;

        return $this;
    }

    public function getProject() {
        return $this->project;
    }

    public function setEnvironment($environment) {
        $this->environment = $environment;

        return $this;
    }

    public function getEnvironment() {
        return $this->environment;
    }

    public function getEnvironmentColored() {
        $colors = [
            'dev' => 'green',
            'staging' => 'yellow',
            'production' => 'red'
        ];

        return '<fg=' . $colors[$this->environment] . '>' . ucfirst($this->environment) . '</fg=' . $colors[$this->environment] . '>';
    }

    public function setOutput($output) {
        $this->output = $output;

        return $this;
    }

    public function getOutput() {
        return $output;
    }

    public static function fromEnvironment($name, $project) {
        if (strlen($name) <= 0) {
            throw new \InvalidArgumentException('Please specify an environment');
        }

        $transfer = new Transfer;
        $transfer->setProject($project);

        $name = strtolower($name);
        switch ($name[0]) {
            case 'd':
                $transfer->setEnvironment('dev');
                break;

            case 's':
                $transfer->setEnvironment('staging');
                break;

            case 'p':
                $transfer->setEnvironment('production');
                break;
            
            default:
                throw new \InvalidArgumentException('Invalid environment, valid environments are development/dev/d, staging/stage/s or production/prod/p');
        }

        return $transfer;
    }

    public function dumpDatabase() {
        // Create hash to avoid collisions
        $fileHash = sha1($this->project->getName() . time());

        $localFilename = sys_get_temp_dir() . '/' . $fileHash . '.sql';
        $remoteFilename = '/tmp/' . $fileHash . '.sql';

        $databaseCredentials = $this->project->getDatabaseCredentials($this->environment);

        // Connect to server
        $this->output->write('Connecting to '. $this->environment .' server... ');

        $credentials = null;
        try {
            $credentials = ($this->environment == 'dev') ? $this->project->getDevelopmentServer() : $this->project->getConfig()->getEnvironment($this->environment);
        } catch (\Exception $e) {
            $this->output->writeln('<error>Error: '. $e->getMessage() .'</error>');
            exit(1);
        }

        $terminal = new RemoteTerminal($credentials, $this->output);

        $this->output->writeln('<info>OK</info>');

        // Perform mysqldump
        $this->output->write('Creating database dump... ');
        $terminal->run("mysqldump -u". $databaseCredentials['username'] ." -p". $databaseCredentials['password'] . " --skip-comments " . $databaseCredentials['database'] . ' > ' . $remoteFilename);
        $this->output->writeln('<info>OK</info>');

        // Download MySQL dump
        $this->output->write('Downloading database dump to local db folder... ');
        $sftp = new RemoteFileSystem($credentials, $this->output);
        $sftp->download($remoteFilename, $localFilename);
        $this->output->writeln('<info>OK</info>');

        // Clean up
        $this->output->write('Cleaning up... ');
        $terminal->run("rm " . $remoteFilename);
        $this->output->writeln('<info>OK</info>');

        return $localFilename;
    }

    public function importDatabase($localFilename) {    
        // Create hash to avoid collisions
        $fileHash = sha1($this->project->getName() . time());
        $remoteFilename = '/tmp/' . $fileHash . '.sql';

        $databaseCredentials = $this->project->getDatabaseCredentials($this->environment);

        // Connect to server
        $this->output->write('Connecting to '. $this->environment .' server... ');

        $credentials = null;
        try {
            $credentials = ($this->environment == 'dev') ? $this->project->getDevelopmentServer() : $this->project->getConfig()->getEnvironment($this->environment);
        } catch (\Exception $e) {
            $this->output->writeln('<error>Error: '. $e->getMessage() .'</error>');
            exit(1);
        }

        $terminal = new RemoteTerminal($credentials, $this->output);

        $this->output->writeln('<info>OK</info>');

        // Upload MySQL dump
        $this->output->write('Uploading database dump to remote server... ');
        $sftp = new RemoteFileSystem($credentials, $this->output);
        $sftp->upload($remoteFilename, $localFilename);

        $this->output->writeln('<info>OK</info>');

        // Import database
        $this->output->write('Importing database... ');
        $terminal->run("mysql -u". $databaseCredentials['username'] ." -p". $databaseCredentials['password'] . " " . $databaseCredentials['database'] . ' < ' . $remoteFilename);
        $this->output->writeln('<info>OK</info>');

        // Clean up
        $this->output->write('Cleaning up... ');
        $terminal->run("rm " . $remoteFilename);
        $this->output->writeln('<info>OK</info>');
    }

    public function dumpUploads() {
        // Create hash to avoid collisions
        $fileHash = sha1($this->project->getName() . time());
        $uploadsFolder = $this->project->getUploadsFolder();

        $localFilename = sys_get_temp_dir() . '/' . $fileHash . '.tar.gz';
        $remoteFilename = '/tmp/' . $fileHash . '.tar.gz';

        // Connect to server
        $this->output->write('Connecting to '. $this->environment .' server... ');

        $credentials = null;
        try {
            $credentials = ($this->environment == 'dev') ? $this->project->getDevelopmentServer() : $this->project->getConfig()->getEnvironment($this->environment);
        } catch (\Exception $e) {
            $this->output->writeln('<error>Error: '. $e->getMessage() .'</error>');
            exit(1);
        }

        $terminal = new RemoteTerminal($credentials, $this->output);
        $this->output->writeln('<info>OK</info>');

        $this->output->write('Creating uploads dump... ');
        $remoteDir = ($environment == 'staging') ? ('/var/www/sites/' . $project->getStagingUrl(false)) : $project->getProductionDirectory();
        $terminal->run('tar zcvf ' . $remoteFilename . ' ' . $remoteDir . '/' . $uploadsFolder);
        $this->output->writeln('<info>OK</info>');

        $this->output->write('Downloading database dump to local db folder... ');
        $sftp = new RemoteFileSystem($credentials, $this->output);
        $sftp->download($remoteFilename, $localFilename);
        $this->output->writeln('<info>OK</info>');

        $this->output->write('Cleaning up... ');
        $terminal->run("rm " . $remoteFilename);
        $this->output->writeln('<info>OK</info>');

        return $localFilename;
    }

}
