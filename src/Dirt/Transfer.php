<?php
namespace Dirt;

use Dirt\Configuration;
use Dirt\Tools\RemoteTerminal;
use Dirt\Tools\RemoteFileSystem;
use Dirt\Tools\LocalTerminal;

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

    public function migrateDatabase($filename, $source) {
        $this->output->write('Migrating database... ');

        $fromUrl = $this->getProject()->urlForEnvironment($source->getEnvironment(), false);
        $toUrl = $this->getProject()->urlForEnvironment($this->getEnvironment(), false);

        // We currently only have a serfix binary of OS X
        $system = $this->getSystem();
        if ($system != 'macosx') {
            $this->output->writeln('<error>Error: Database migrations is currently only supported on OS X, sorry.</error>');
            return;
        }

        $serfixBinary = dirname(__FILE__) . '/../../bin/' . $system . '/serfix';

        // Do find and replace and run serfix (https://github.com/astockwell/serfix) on the file
        // to correctly migrate any PHP serialization objects
        $terminal = new LocalTerminal($this->project->getDirectory(), $this->output);
        $terminal->run('sed -i "" "s/' . $fromUrl . '/' . $toUrl . '/g" ' . $filename);
        $terminal->run($serfixBinary . ' ' . $filename);

        $this->output->writeln('<info>OK</info>');
    }

    /**
     * Get the operating system for the current platform.
     * Inspired by Laravel Cashier
     * https://github.com/laravel/cashier/blob/4.0/src/Laravel/Cashier/Invoice.php
     *
     * @return string
     */
    protected function getSystem()
    {
        $uname = strtolower(php_uname());
        if (strpos($uname, 'darwin') !== false) {
            return 'macosx';
        } elseif (strpos($uname, 'win') !== false) {
            return 'windows';
        } elseif (strpos($uname, 'linux') !== false) {
            return PHP_INT_SIZE === 4 ? 'linux-i686' : 'linux-x86_64';
        } else {
            throw new \RuntimeException('Unknown operating system.');
        }
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

        if ($this->environment == 'dev') {
            $this->output->write('Creating uploads dump... ');
            $terminal = new LocalTerminal($this->project->getDirectory(), $this->output);
            $terminal->run('tar zcvf ' . $localFilename . ' ' . $uploadsFolder);
            $this->output->writeln('<info>OK</info>');
        } else {
            $remoteFilename = '/tmp/' . $fileHash . '.tar.gz';

            // Connect to server
            $this->output->write('Connecting to '. $this->environment .' server... ');
            $credentials = $this->project->getConfig()->getEnvironment($this->environment);

            $terminal = new RemoteTerminal($credentials, $this->output);
            $this->output->writeln('<info>OK</info>');

            $this->output->write('Creating uploads dump... ');
            $terminal->run('cd ' . $this->project->getFolderForEnvironment($this->environment) . ' && tar zcvf ' . $remoteFilename . ' ' . $uploadsFolder);
            $this->output->writeln('<info>OK</info>');

            $this->output->write('Downloading files to local folder... ');
            $sftp = new RemoteFileSystem($credentials, $this->output);
            $sftp->download($remoteFilename, $localFilename);
            $this->output->writeln('<info>OK</info>');

            $this->output->write('Cleaning up on remote... ');
            $terminal->run("rm " . $remoteFilename);
            $this->output->writeln('<info>OK</info>');
        }

        return $localFilename;
    }

    public function importUploads($localFilename) {
        if ($this->environment == 'dev') {
            // Extract files
            $this->output->write('Extracting files... ');
            $terminal = new LocalTerminal($this->project->getDirectory(), $this->output);
            $terminal->run('mv ' . $localFilename . ' ' . $this->project->getDirectory() . ' && tar zxvf ' . basename($localFilename));
            $this->output->writeln('<info>OK</info>');

            $this->output->write('Applying permissions... ');
            $terminal->run('chmod -R 777 ' . $this->project->getUploadsFolder());
            $this->output->writeln('<info>OK</info>');

            return $this->project->getDirectory() . '/' . basename($localFilename);
        } else {
            // Upload files
            $credentials = $this->project->getConfig()->getEnvironment($this->environment);
            
            $this->output->write('Uploading files... ');
            $sftp = new RemoteFileSystem($credentials, $this->output);
            $sftp->upload($this->project->getFolderForEnvironment($this->environment) . '/' . basename($localFilename), $localFilename);
            $this->output->writeln('<info>OK</info>');

            // Extract files
            $this->output->write('Extracting files... ');
            $terminal = new RemoteTerminal($credentials, $this->output);
            $terminal->run('cd ' . $this->project->getFolderForEnvironment($this->environment) . ' && tar zxvf ' . basename($localFilename));
            $this->output->writeln('<info>OK</info>');

            $this->output->write('Cleaning up on remote... ');
            $terminal->run('cd ' . $this->project->getFolderForEnvironment($this->environment) . ' && rm ' . basename($localFilename));
            $this->output->writeln('<info>OK</info>');

            $this->output->write('Applying permissions... ');
            $terminal->run('cd ' . $this->project->getFolderForEnvironment($this->environment) . ' && chmod -R 777 ' . $this->project->getUploadsFolder());
            $this->output->writeln('<info>OK</info>');
        }

        return $localFilename;
    }

}
