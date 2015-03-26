<?php
namespace Dirt\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Process\Process;
use Dirt\Project;
use Dirt\Transfer;

class TransferUploadsCommand extends Command
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
            ->setName('transfer:uploads')
            ->setDescription('Transfers uploaded files from one environment to another')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'development/dev/d, staging/stage/s or production/prod/p'
            )
            ->addArgument(
                'destination',
                InputArgument::REQUIRED,
                'development/dev/d, staging/stage/s or production/prod/p'
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
        $project = Project::fromDirtfile($dirtfileName);
        $project->setConfig($this->config);

        $dialog = $this->getHelperSet()->get('dialog');

        // Check if uploads folder exists for project
        if ($project->getUploadsFolder() === null) {
            throw new \RuntimeException('This project does not have an uploads folder');
        }

        // Initialize transfer objects
        $source = Transfer::fromEnvironment($input->getArgument('source'), $project)
            ->setOutput($output);

        $destination = Transfer::fromEnvironment($input->getArgument('destination'), $project)
            ->setOutput($output);

        $output->writeln('     __________
   /          /|
 /__________/  |
 |________ |   |
 /_____  /||   |    Transfer uploaded files
|".___."| ||   |    ' . $source->getEnvironmentColored() . ' ⇾ ' . $destination->getEnvironmentColored() . '
|_______|/ |   |
 || .___."||  /
 ||_______|| /
 |_________|/');

        // Ask for confirmation since this could possibly be destructive
        if (!$dialog->askConfirmation(
                $output,
                '<question>This will overwrite any uploads in the <fg=black;bg=cyan;options=bold>' . $destination->getEnvironment() . '</fg=black;bg=cyan;options=bold> environment, are you sure that you want to proceed?</question> ',
                false
            ))
        {
            return;
        }

        // Dump the uploads and pack them into a local archive
        $filename = $source->dumpUploads();

        // Import the uploads to the source
        $filename = $destination->importUploads($filename);

        // Clean up local file
        @unlink($filename);
    }

}
