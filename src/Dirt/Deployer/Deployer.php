<?php
namespace Dirt\Deployer;

use Dirt\Configuration;

abstract class Deployer
{
    protected $input;
    protected $output;
    protected $dialog;
    protected $project;
    protected $config;
    protected $verbose = false;
    protected $yes = false;
    protected $no = false;
    
    public function setInput($input)
    {
        $this->input = $input;
    }

    public function setOutput($output)
    {
        $this->output = $output;
    }

    public function setDialog($dialog)
    {
        $this->dialog = $dialog;
    }

    public function setProject($project)
    {
        $this->project = $project;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    public function setYes($yes)
    {
        $this->yes = $yes;
    }

    public function setNo($no)
    {
        $this->no = $no;
    }

    public function dumpDatabase($shouldImport = false)
    {
        // Create hash to avoid collisions
        $fileHash = sha1($this->project->getName() . time());

        $environment = $this->getEnvironment();

        $structureFile = $this->project->getDirectory() . '/db/' . $environment . '_structure.sql';
        $contentFile = $this->project->getDirectory() . '/db/' . $environment . '_content.sql';
        $databaseCredentials = $this->project->getDatabaseCredentials($environment);

        // Make sure that db directory exists
        @mkdir($this->project->getDirectory() . '/db');

        // Check if file already exists
        if ($this->no) {
            return;
        }
        
        if (!$this->yes) {
            if (file_exists($structureFile) || file_exists($contentFile)) {
                if (!$this->dialog->askConfirmation(
                        $this->output,
                        '<question>This will override existing '. $environment .' database dumps in the "db/" folder, do you want to continue?</question> ',
                        false
                    ))
                {
                    return;
                }
            }
        }

        // Connect to server
        $this->output->write('Connecting to '. $environment .' server... ');

        $credentials = null;
        try {
            $credentials = ($environment == 'dev') ? $this->project->getDevelopmentServer() : $this->config->environments->$environment;
        } catch (\Exception $e) {
            $this->output->writeln('<error>Error: '. $e->getMessage() .'</error>');
            exit(1);
        }

        $ssh = new \Net_SSH2($credentials->hostname, isset($credentials->port) ? $credentials->port : 22);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($credentials->keyfile));
        if (!$ssh->login($credentials->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        }
        $this->output->writeln('<info>OK</info>');

        // Perform mysqldump
        $this->output->write('Creating database dump... ');
        $mysqldumpCmd = "mysqldump -u". $databaseCredentials['username'] ." -p". $databaseCredentials['password'];

        // Structure
        $response = $ssh->exec($mysqldumpCmd . " --no-data --skip-comments " . $databaseCredentials['database'] . ' > /tmp/'. $environment .'_'. $fileHash .'_structure.sql');
        if (strlen($response) != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }

        // Content
        $response = $ssh->exec($mysqldumpCmd . " --skip-extended-insert --skip-comments --no-create-info " . $databaseCredentials['database'] . ' > /tmp/'. $environment .'_'. $fileHash .'_content.sql');
        if (strlen($response) != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }
        $this->output->writeln('<info>OK</info>');

        // Download MySQL dump
        $this->output->write('Downloading database dump to local db folder... ');

        $sftp = new \Net_SFTP($credentials->hostname, isset($credentials->port) ? $credentials->port : 22);
        if (!$sftp->login($credentials->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            exit(1);
        } else {
            $sftp->get('/tmp/'. $environment .'_'. $fileHash .'_structure.sql', $this->project->getDirectory() . '/db/'. $environment .'_structure.sql');
            $sftp->get('/tmp/'. $environment .'_'. $fileHash .'_content.sql', $this->project->getDirectory() . '/db/'. $environment .'_content.sql');
            $this->output->writeln('<info>OK</info>');
        }

        // Clean up
        $this->output->write('Cleaning up... ');
        $response = $ssh->exec('rm /tmp/'. $environment .'_'. $fileHash .'_structure.sql');
        if (strlen($response) != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }

        $response = $ssh->exec('rm /tmp/'. $environment .'_'. $fileHash .'_content.sql');
        if (strlen($response) != 0) {
            $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
            exit(1);
        }

        $this->output->writeln('<info>OK</info>');

        // Perform import if necessary
        if ($shouldImport) {
            // Connect to server
            $this->output->write('Connecting to development server... ');

            $devCredentials = $this->project->getDevelopmentServer();
            $devSSH = new \Net_SSH2($devCredentials->hostname, $devCredentials->port);
            $key = new \Crypt_RSA();
            $key->loadKey(file_get_contents($devCredentials->keyfile));
            if (!$devSSH->login($devCredentials->username, $key)) {
                $this->output->writeln('<error>Error: Authentication failed</error>');
                exit(1);
            }
            $this->output->writeln('<info>OK</info>');

            // Import MySQL dump
            $this->output->write("\t" . 'Importing database dump... ');
            $devDatabaseCredentials = $this->project->getDatabaseCredentials('dev');
            $response = $devSSH->exec('mysql -u'. $devDatabaseCredentials['username'] .' -p'. $devDatabaseCredentials['password'] .' '. $devDatabaseCredentials['database'] .' < /var/www/site/db/'. $environment .'_structure.sql');
            if ($devSSH->getExitStatus() != 0) {
                $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
                exit(1);
            }
            
            $response = $devSSH->exec('mysql -u'. $devDatabaseCredentials['username'] .' -p'. $devDatabaseCredentials['password'] .' '. $devDatabaseCredentials['database'] .' < /var/www/site/db/'. $environment .'_content.sql');
            if ($devSSH->getExitStatus() != 0) {
                $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
                exit(1);
            }

            $this->output->writeln('<info>OK</info>');
        }
    }

    abstract function getEnvironment();
    abstract function deploy();
    abstract function undeploy();
}