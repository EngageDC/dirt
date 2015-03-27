<?php
namespace Dirt\Framework;

use Symfony\Component\Process\Process;
use Dirt\Configuration;
use Dirt\TemplateHandler;

class Laravel5Framework extends Framework
{
	/**
	 * Full name of the Framework
	 * @return string
	 */
    public function getName()
    {
    	return 'Laravel 5';
    }

    /**
     * One or more command line shortcuts for the framework
     * must be lowercase.
     * @return array
     */
    public function getShortcuts()
    {
    	return array('laravel5', 'l5', 'laravel');
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
        unlink($project->getDirectory() . '/public/readme.md');

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
            $process = new Process('chmod -R 777 storage', $project->getDirectory());
            $process->run();
        }
        elseif ($environment == 'staging')
        {
            $ssh->exec('chmod -R 777 /var/www/sites/' . $project->getStagingUrl(false) . '/storage');
            $ssh->exec('cd /var/www/sites/' . $project->getStagingUrl(false) . '/ && composer install');
            $ssh->exec('cd /var/www/sites/' . $project->getStagingUrl(false) . '/ && php artisan migrate --env=staging');
            $ssh->exec('cd /var/www/sites/' . $project->getStagingUrl(false) . '/ && php artisan optimize');
        }
        elseif ($environment == 'production')
        {
            // Create symlink
            $ssh->exec('cd ' . $project->getProductionDirectory() . ' && ln -sf public html');

            // Update permissions
            $ssh->exec('chmod -R 777 ' . $project->getProductionDirectory() . '/storage');
        }
    }

}
