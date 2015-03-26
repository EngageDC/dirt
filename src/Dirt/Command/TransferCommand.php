<?php
namespace Dirt\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
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
            ->setName('transfer')
            ->setDescription('Transfers a database dump and uploads from one environment to another')
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
        $this->runCommand('transfer:db', $input, $output);
        $output->writeln('');
        $this->runCommand('transfer:uploads', $input, $output);
    }

    private function runCommand($name, $input, $output) {
        $command = $this->getApplication()->find($name);

        $arguments = array(
            'command'     => $name,
            'source'      => $input->getArgument('source'),
            'destination' => $input->getArgument('destination')
        );

        $input = new ArrayInput($arguments);
        $returnCode = $command->run($input, $output);
    }

}
