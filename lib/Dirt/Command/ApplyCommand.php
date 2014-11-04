<?php
namespace Dirt\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Dirt\Project;
use Dirt\Framework\Framework;
use Dirt\Deployer\Deployer;
use Dirt\Deployer\StagingDeployer;

class ApplyCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('apply')
            ->setDescription('Apply\'s changes from Dirtfile.json to the Vagrantfile and other relevant files, will also reconfigure the local environment')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');

        // Load project metadata
        $dirtfileName = getcwd() . '/Dirtfile.json';
        if (!file_exists($dirtfileName)) {
            throw new \RuntimeException('Not a valid project directory, Dirtfile.json could not be found.');
        }
        $project = Project::fromDirtfile($dirtfileName);

        // Template variables
        $devDatabaseCredentials = $project->getDatabaseCredentials('dev');

        $variables = array(
            '__PROJECT_NAME__' => $project->getName(false),
            '__PROJECT_NAME_SIMPLE__' => $project->getName(true),
            '__PROJECT_DESCRIPTION__' => $project->getDescription(),
            '__DEV_URL__' => $project->getDevUrl(false),
            '__STAGING_URL__' => $project->getStagingUrl(false),
            '__DATABASE_USERNAME__' => $devDatabaseCredentials['username'],
            '__DATABASE_PASSWORD__' => $devDatabaseCredentials['password'],
            '__DATABASE_NAME__' => $devDatabaseCredentials['database'],
            '__IPADDRESS__' => $project->getIpAddress(),
        );

        // Re-save the configuration file in case it has changed
        $project->save();

        // Check if Vagrantfile exists
        if (file_exists($project->getDirectory() . '/Vagrantfile'))
        {
            if (!$dialog->askConfirmation(
                    $output,
                    '<question>Warning! This will overwrite the existing Vagrantfile, do you want to continue?</question> ',
                    false
                ))
            {
                return;
            }
        }

        $output->write('Updating Vagrantfile... ');
        $vagrantTemplate = file_get_contents(dirname(__FILE__) . '/../Templates/Vagrantfile');
        $vagrantTemplate = str_replace(array_keys($variables), array_values($variables), $vagrantTemplate);
        file_put_contents($project->getDirectory() . '/Vagrantfile', $vagrantTemplate);
        $output->writeln('<info>OK</info>');

        if (file_exists($project->getDirectory() . '/Berksfile')) {
            $output->write('Deleting Berksfile as it is no longer necessary... ');
            @unlink($project->getDirectory() . '/Berksfile');
            $output->writeln('<info>OK</info>');
        }

        if ($project->getFramework() !== FALSE) {
            $output->write('Reconfiguring local environment... ');
            $project->getFramework()->configureEnvironment('dev', $project);
            $output->writeln('<info>OK</info>');
        }
    }

}