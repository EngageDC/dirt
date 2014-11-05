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
use Dirt\Deployer\ProductionDeployer;

class DeployCommand extends Command
{
    private $config;

    public function __construct(\Dirt\Configuration $configuration) {
        parent::__construct();

        $this->config = $configuration;
    }
    
    protected function configure()
    {
        $this
            ->setName('deploy')
            ->setDescription('Deploys the project to the staging or production environment')
            ->addArgument(
                'environment',
                InputArgument::REQUIRED,
                'staging/stage/s or production/prod/p'
            )
            ->addOption(
                'undeploy',
                'u',
                InputOption::VALUE_NONE,
                'Completely removes the site from the remote server'
            )
            ->addOption(
                'verbose',
                'v',
                InputOption::VALUE_NONE,
                'Be verbose'
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Say yes to all prompts'
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

        $environmentArgument = strtolower($input->getArgument('environment'));
        if ($environmentArgument[0] == 's')
        {
            $deployer = new StagingDeployer();
        }
        elseif ($environmentArgument[0] == 'p')
        {
            if ($input->getOption('yes') || $dialog->askConfirmation(
                    $output,
                    '<question>This will make a complete physical copy of the files from the staging environment to the production server, do you want to continue?</question> ',
                    false
                ))
            {
                $deployer = new ProductionDeployer();
            }
            else
            {
                return;
            }
        }
        else
        {
            throw new \InvalidArgumentException('Invalid environment, valid environments are staging/stage/s or production/prod/p');
        }

        // Start deployer
        $deployer->setInput($input);
        $deployer->setOutput($output);
        $deployer->setDialog($this->getHelperSet()->get('dialog'));
        $deployer->setProject($project);
        $deployer->setConfig($this->config);
        $deployer->setVerbose($input->getOption('verbose'));
        $deployer->setYes($input->getOption('yes'));

        // Undeploy?
        if ($input->getOption('undeploy'))
        {
            if ($input->getOption('yes') || $dialog->askConfirmation(
                    $output,
                    '<question>This will completely remove the project from the remote server, do you want to continue?</question> ',
                    false
                ))
            {
                $deployer->undeploy();
            }
        }
        else
        {
            // Deploy!
            $deployer->deploy();
        }
    }

}