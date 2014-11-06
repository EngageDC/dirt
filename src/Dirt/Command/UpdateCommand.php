<?php
namespace Dirt\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class UpdateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Updates the dirt framework to the newest version')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {        
        $output->writeln('Updating dirt... ');
        
        $process = new Process('git fetch --all && git reset --hard origin/master', dirname(__FILE__) . '/../../../');
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
    }

}