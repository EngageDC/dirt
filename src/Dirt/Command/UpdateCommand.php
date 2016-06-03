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
        $basePath = dirname(__FILE__) . '/../../../';


        $output->writeln('Updating dirt... ');
        $process = new Process('git fetch --all && git reset --hard origin/master', $basePath);
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        $output->writeln('Updating dependencies... ');
        $process = new Process('composer install', $basePath);
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (file_exists($basePath . 'team')) {
            $output->writeln('Updating team configuration... ');

            $process = new Process('git fetch --all && git reset --hard origin/master', $basePath . 'team');
            $process->setTimeout(3600);
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        }


        $this->writeUpdateMessage($output);
    }


    protected function writeUpdateMessage($output) {

        $composer = json_decode(file_get_contents(dirname(__FILE__) . '/../../../composer.json'));

        $output->writeln("<info>Dirt updated to version" . $composer->version . ".</info>");

        $output->writeln("<comment>New features:</comment>");
        $output->writeln("1. New Command: <comment>dirt branch NAME_OF_BRANCH</comment> // creates a branch and pushes branch to repo");
        $output->writeln("2. New Option for Dirt Deploy: <comment>dirt deploy branch</comment> // pushes code to current working branch");
        $output->writeln("3. Added .DS_Store to .gitignore by default.");

    }

}