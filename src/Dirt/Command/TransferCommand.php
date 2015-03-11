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

class TransferCommand extends Command
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
            ->setName('transfers')
            ->setDescription('Transfers a database dump from one environment to another')
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

        // Initialize transfer objects
        $source = Transfer::fromEnvironment($input->getArgument('source'), $project)->setOutput($output);
        $destination = Transfer::fromEnvironment($input->getArgument('destination'), $project)->setOutput($output);

        // Show table with details
        $table = new Table($output);
        $table->getStyle()->setCellHeaderFormat('%s');
        $table->setHeaders(['Source', 'Destination'])
            ->setRows([
                [
                    $source->getEnvironmentColored(),
                    $destination->getEnvironmentColored()
                ]
            ]
        );
        $table->render();

        // Ask for confirmation since this could possibly be destructive
        if (!$dialog->askConfirmation(
                $output,
                '<question>This will override any changes in the <fg=black;bg=cyan;options=bold>' . $destination->getEnvironment() . '</fg=black;bg=cyan;options=bold> database, are you sure that you want to proceed?</question> ',
                false
            ))
        {
            return;
        }

        // Dump the source database
        $filename = $source->dumpDatabase();

        // Import the database dump
        $destination->importDatabase($filename);

        // Clean up locally
        @unlink($filename);
    }

}
