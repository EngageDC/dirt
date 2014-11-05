<?php
namespace Dirt\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Dirt\Project;

class OpenCommand extends Command
{
    private $config;

    public function __construct(\Dirt\Configuration $configuration) {
        parent::__construct();

        $this->config = $configuration;
    }
    
    protected function configure()
    {
        $this
            ->setName('open')
            ->setDescription('Opens the website in your browser')
            ->addArgument(
                'environment',
                InputArgument::OPTIONAL,
                'development/dev/d or staging/stage/s or production/prod/p'
            )
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

        // Validate environment
        $deployer = null;

        $environmentArgument = strtolower($input->getArgument('environment') ? $input->getArgument('environment') : 'development');
        if ($environmentArgument[0] == 'p')
        {
            $url = $project->getProductionUrl();
        }
        elseif ($environmentArgument[0] == 's')
        {
            $url = $project->getStagingUrl();
        }
        else
        {
            // Check if Virtual Machine is running
            $vmIsRunning = false;

            $process = new Process('vagrant status');
            $process->run(function ($type, $buffer) use (&$vmIsRunning) {
                if (strstr($buffer, 'The VM is running. To stop this VM') !== FALSE) {
                    $vmIsRunning = true;
                }
            });

            if (!$process->isSuccessful() || !$vmIsRunning) {
                throw new \RuntimeException('Local virtual machine is not available, make sure local virtual machine is running by executing "vagrant up"');
            }

            $url = $project->getDevUrl();
        }

        $process = new Process((defined('PHP_WINDOWS_VERSION_BUILD') ? 'start' : 'open') . ' ' . $url);
        $process->run();
    }

}