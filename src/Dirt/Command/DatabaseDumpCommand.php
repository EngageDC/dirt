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
use Dirt\Deployer\DevDeployer;
use Dirt\Deployer\StagingDeployer;

class DatabaseDumpCommand extends Command
{
    private $input;
    private $output;

    private $project;
    private $config;

    public function __construct(\Dirt\Configuration $configuration) {
        parent::__construct();

        $this->config = $configuration;
    }

    protected function configure()
    {
        $this
            ->setName('database:dump')
            ->setDescription('Generates a database dump for the given environment and stores it in the db/ folder')
            ->addArgument(
                'environment',
                InputArgument::REQUIRED,
                'development/dev/d, staging/stage/s or production/prod/p'
            )
            ->addOption(
                'import',
                'i',
                InputOption::VALUE_NONE,
                'Import the database dump on the local environment'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Load project metadata
        $dirtfileName = getcwd() . '/Dirtfile.json';
        if (!file_exists($dirtfileName)) {
            throw new \RuntimeException('Not a valid project directory, Dirtfile.json could not be found.');
        }
        $project = Project::fromDirtfile($dirtfileName, $this->config);

        // Validate environment
        $dialog = $this->getHelperSet()->get('dialog');
        $deployer = null;

        $environmentArgument = strtolower($input->getArgument('environment'));
        if ($environmentArgument[0] == 'd')
        {
            $deployer = new DevDeployer();
        }
        elseif ($environmentArgument[0] == 's')
        {
            $deployer = new StagingDeployer();
        }
        elseif ($environmentArgument[0] == 'p')
        {
            $output->writeln('<comment>Dumping from production is not implemented yet.</comment>');
            exit(1);
        }
        else
        {
            throw new \InvalidArgumentException('Invalid environment, valid environments are development/dev/d, staging/stage/s or production/prod/p');
        }

        // Import flag is only applicable for non-dev environments
        $shouldImport = $input->getOption('import');
        if ($deployer->getEnvironment() == 'dev') {
            $shouldImport = false;
        }

        if ($shouldImport) {
            if (!$dialog->askConfirmation(
                    $output,
                    '<question>This will override the local database, do you want to continue?</question> ',
                    false
                ))
            {
                return;
            }
        }

        // Start deployer
        $deployer->setInput($input);
        $deployer->setOutput($output);
        $deployer->setDialog($dialog);
        $deployer->setProject($project);
        $deployer->setConfig($this->config);

        // Dump database
        $deployer->dumpDatabase($shouldImport);
    }
    
}