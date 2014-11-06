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
use Dirt\TemplateHandler;

class ApplyCommand extends Command
{
    private $config;

    public function __construct(\Dirt\Configuration $configuration) {
        parent::__construct();

        $this->config = $configuration;
    }
    
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
        $project = Project::fromDirtfile($dirtfileName, $this->config);

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

        // Re-save the configuration file in case it has changed
        $project->save();

        // Update Vagrantfile
        $output->write('Updating Vagrantfile... ');
        $templateHandler = new TemplateHandler();
        $templateHandler->setProject($project);
        $templateHandler->writeTemplate('Vagrantfile');
        $output->writeln('<info>OK</info>');

        // Display warning if Berksfile exists
        // TODO: Remove me when this is no longer relevant
        if (file_exists($project->getDirectory() . '/Berksfile')) {
            $output->writeln('<comment>Note: The Berksfile is no longer necessary, you can safely delete it.</comment>');
        }

        // Configure framework if ncessary
        if ($project->getFramework() !== FALSE) {
            $output->write('Reconfiguring local environment... ');
            $project->getFramework()->configureEnvironment('dev', $project);
            $output->writeln('<info>OK</info>');
        }
    }

}