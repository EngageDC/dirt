<?php
namespace Dirt\Framework;

use Symfony\Component\Process\Process;
use Dirt\Configuration;
use Dirt\TemplateHandler;

class Laravel4Framework extends Framework
{
	/**
	 * Full name of the Framework
	 * @return string
	 */
    public function getName()
    {
    	return 'Laravel 4';
    }

    /**
     * One or more command line shortcuts for the framework
     * must be lowercase.
     * @return array
     */
    public function getShortcuts()
    {
    	return array('laravel4', 'laravel', 'l4');
    }

    /**
     * Start the framework installation process
     * @param Project $project 
     * @param function $progressCallback 
     */
    public function install($project, $progressCallback = null)
    {
        // Download latest version
        $filename = $this->downloadFile('https://github.com/laravel/laravel/archive/master.zip', $project->getDirectory(), $progressCallback);

        // Extract to location
        $this->extractArchive($filename, $project->getDirectory(), $progressCallback);

        // Remove README file
        unlink($project->getDirectory() . '/public/README.md');

        // Get array of all source files
        $sourceDir = $project->getDirectory() . '/public/';
        $destinationDir = $project->getDirectory() . '/';
        $files = scandir($sourceDir);
        
        // Cycle through all source files
        foreach ($files as $file)
        {
            if (in_array($file, array('.', '..', 'public')))
                continue;
            
            // Move file
            rename($sourceDir . $file, $destinationDir . $file);
        }

        // Get array of all source files
        $sourceDir = $project->getDirectory() . '/public/public/';
        $destinationDir = $project->getDirectory() . '/public/';
        $files = scandir($sourceDir);
        
        // Cycle through all source files
        foreach ($files as $file)
        {
            if (in_array($file, array('.', '..')))
                continue;
            
            // Move file
            rename($sourceDir . $file, $destinationDir . $file);
        }
        rmdir($project->getDirectory() . '/public/public');

        // Create config directory for each environment
        $this->createDatabaseConfig($project->getDirectory(), $project->getDatabaseCredentials('dev'), 'dev');
        $this->createDatabaseConfig($project->getDirectory(), $project->getDatabaseCredentials('staging'), 'staging');

        // Add environments to start.php
        $this->updateEnvironmentDetection($project->getDirectory());

        // Merge Laravel gitignore file with gitignore template
        $originalGitignore = file_get_contents($project->getDirectory() . '/.gitignore');

        $templateHandler = new TemplateHandler();
        $templateHandler->setProject($project);
        $templateHandler->writeTemplate('gitignore');

        file_put_contents($project->getDirectory() . '/.gitignore', $originalGitignore . PHP_EOL . file_get_contents($project->getDirectory() . '/.gitignore'));

        // Run "composer install"
        $progressCallback("\t" . 'Running "composer install" (this may take a few minutes) ');
        $process = new Process('composer install', $project->getDirectory());
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $progressCallback('<error>Error: '. $process->getErrorOutput() .'</error>' . PHP_EOL);
            exit(1);
        } else {
            $progressCallback('<info>OK</info>' . PHP_EOL);
        }
    }

    public function configureEnvironment($environment, $project, $ssh = NULL)
    {
        if ($environment == 'dev') {
            $process = new Process('chmod -R 777 app/storage', $project->getDirectory());
            $process->run();
        }
        elseif ($environment == 'staging')
        {
            $ssh->exec('chmod -R 777 /var/www/sites/' . $project->getStagingUrl(false) . '/app/storage');
            $ssh->exec('cd /var/www/sites/' . $project->getStagingUrl(false) . '/ && php artisan migrate --env=staging');
            $ssh->exec('cd /var/www/sites/' . $project->getStagingUrl(false) . '/ && php artisan optimize');
            $ssh->exec('cd /var/www/sites/' . $project->getStagingUrl(false) . '/ && composer install');
        }
        elseif ($environment == 'production')
        {
            // Create symlink
            $ssh->exec('cd ' . $project->getProductionDirectory() . ' && ln -sf public html');

            // Update permissions
            $ssh->exec('chmod -R 777 ' . $project->getProductionDirectory() . '/app/storage');
        }
    }

    private function createDatabaseConfig($directory, $databaseCredentials, $environment)
    {
        // Only continue if we are in an environment that supports database handling
        if ($environment != 'dev' && $environment != 'staging')
            return;

        // Define config directory
        $environmentDirectory = $directory . '/app/config/' . $environment;

        // Create config directory
        if (!file_exists($environmentDirectory))
            mkdir($environmentDirectory);

        // Copy default database config over
        copy($directory . '/app/config/database.php', $environmentDirectory . '/database.php');

        // Load config file
        $sourceConfig = file_get_contents($environmentDirectory . '/database.php');
        $configLines = explode("\n", $sourceConfig);

        $isInMySQLSection = false;
        foreach ($configLines as &$line) {
            if (strpos($line, "'driver'") !== FALSE && strpos($line, "'mysql'") !== FALSE) {
                $isInMySQLSection = true;
            }
            if (strpos($line, "'driver'") !== FALSE && strpos($line, "'pgsql'") !== FALSE) {
                $isInMySQLSection = false;
            }

            if ($isInMySQLSection)
            {
                if (preg_match("/'(.*)'\s+=>\s+('.*',)$/i", $line, $match)) {
                    switch ($match[1]) {
                        case 'username':
                        case 'database':
                        case 'password':
                            $line = str_replace($match[2], "'" . $databaseCredentials[$match[1]] . "',", $line);
                            break;
                    }
                }
            }
        }

        // Save config file
        $finalConfig = implode("\n", $configLines);
        file_put_contents($environmentDirectory . '/database.php', $finalConfig);
    }

    private function updateEnvironmentDetection($directory)
    {
        // Define environments
        $validEnvironments = array(
            'dev' => '*.local',
            'staging' => 'stage'
        );

        // Load config file
        $sourceConfig = file_get_contents($directory . '/bootstrap/start.php');
        $configLines = explode("\n", $sourceConfig);

        $isInMySQLSection = false;
        foreach ($configLines as &$line) {
            if (strpos($line, "homestead") !== FALSE) {
                $line = '';

                foreach ($validEnvironments as $name => $host) {
                    $line .= "\t" . "'". $name ."' => array('". $host ."')," . "\n";
                }
            }
        }

        // Save config file
        $finalConfig = implode("\n", $configLines);
        file_put_contents($directory . '/bootstrap/start.php', $finalConfig);
    }
}