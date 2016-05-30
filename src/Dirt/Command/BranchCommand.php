<?php

namespace Dirt\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Dirt\Project;
use Dirt\Configuration;
use Dirt\Framework\Framework;
use Dirt\Deployer\Deployer;
use Dirt\Deployer\StagingDeployer;
use Dirt\TemplateHandler;
use Dirt\Tools\LocalTerminal;
use Dirt\Tools\RemoteTerminal;
use Dirt\Tools\RemoteFileSystem;
use Dirt\Tools\GitBuilder;
use Dirt\Tools\MySQLBuilder;



class BranchCommand extends Command 
{
    
    private $config;
    
    private $output;
    
    private $terminal;
    
    private $git;
    
    /**
    * @var string
    **/
    private $branch_name;
    
    public function __construct(\Dirt\Configuration $configuration) {
        parent::__construct();

        $this->config = $configuration;
    }
    
    
    protected function configure()
    {
        $this
            ->setName('branch')
            ->setDescription('create a git branch')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'What is the name of the branch you want to create?'
            )
            ->addOption(
               'skip-repo',
               null
            )
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        
        //Set branch name as class var
        $this->branch_name = $input->getArgument('name');
        $this->output = $output;
        $this->terminal = new LocalTerminal('1', $this->output);
        $this->git = new GitBuilder();
        
        
        $this->output->write('Checking if branch already exists...');
        $this->checkForDuplicate();
        
        $output->write('Creating new branch locally... ');
        $this->createLocal();
        

        if ($input->getOption('skip-repo')) {
            $output->writeln('Wrapping up... ');
            $output->writeln('<comment>You will need to manually deploy your branch to the repo.</comment> ');
        }
        
        $this->output->write('Pushing branch to repo... ');
        $this->pushToRepo();
        
        
        $this->output->write('Switching to branch ' . $this->branch_name . '...');
        $this->checkOutBranch();

        $output->writeln('<info>Done</info>');
    }
    
    
    /**
    * Checks on the existence of the named branch
    *
    * @param none
    **/
    protected function checkForDuplicate() {
        
        $branches = $this->terminal->run($this->git->branch());
        
        if (strpos($branches, $this->branch_name) !== false ) {
            $this->output->write(PHP_EOL);
            $this->output->writeln('<error>A branch named ' . $this->branch_name . ' already exists.</error>');
            exit();
        }
        
        
        $this->output->write('<info>Ok</info>');
        $this->output->write(PHP_EOL);
        
        
    }
    
    /**
    * Creates the branch locally first...
    *
    * @param none
    **/
    protected function createLocal() {
        
        $this->terminal->run($this->git->branch(strtolower($this->branch_name)));
        
        $this->output->write('<info>Ok</info>');
        $this->output->write(PHP_EOL);
        
    }
    
    
    protected function pushToRepo() {
        
        $this->terminal->run($this->git->push('origin ' . $this->branch_name));
        $this->output->write('<info>Ok</info>');
        $this->output->write(PHP_EOL);
        
    }
    
    
    protected function checkOutBranch() {
        
        $terminal->run($git->checkout($this->branch_name));
        $this->output->write('<info>Ok</info>');
        $this->output->write(PHP_EOL);
        
        $this->output->writeln('<comment>You are now working on branch ' . $this->branch_name . '</comment>');
        
    }
    
    
}