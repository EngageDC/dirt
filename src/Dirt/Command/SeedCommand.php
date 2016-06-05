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
use Dirt\TemplateHandler;
use Dirt\Repositories\VersionControlRepositoryGitLab;
use Dirt\Repositories\VersionControlRepositoryGitHub;
use Dirt\Tools\LocalTerminal;
use Dirt\Tools\GitBuilder;

class SeedCommand extends Command
{
    private $config;
    private $project;

    private $input;
    private $output;
    
    private $terminal;
    private $git;
    
    private $seed;
    private $teamSeed;

    public function __construct(\Dirt\Configuration $configuration) {
        parent::__construct();

        $this->config = $configuration;
    }

    protected function configure()
    {
        $this->setName('seed')
             ->setDescription('Seed project with starter files');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        
        $dirtfileName = getcwd() . '/Dirtfile.json';
        if (!file_exists($dirtfileName)) {
            throw new \RuntimeException('Not a valid project directory, Dirtfile.json could not be found.');
        }
        $this->project = Project::fromDirtfile($dirtfileName);
        
        $this->terminal = new LocalTerminal(getcwd(), $this->output);
        $this->git = new GitBuilder();
        
        if (!$this->project->hasSeed() && !$this->config->hasTeamSeed()) {
            throw new \RuntimeException('No seed information found.');
        } else {
            $this->seed = $this->project->getSeed();
            $this->teamSeed = $this->config->getTeamSeed();
        }
        
        
        $this->output->write('Checking for build files ...');
        if (!empty($this->seed->build)) {
            $this->output->write('<info>Ok</info>' . PHP_EOL);
            
            $this->output->write('Attempting to clone build files...');
            $this->getBuild();
            
        } else {
            
            
            $this->output->write('<comment> None found.</comment>' . PHP_EOL);
            $this->output->writeln('Continuing...');
            
        }
        
       
    }
    
    
    /**
    * Clone build files
    *
    * @param: none;
    **/
    protected function getBuild() {
        
        //Get git repo name
        preg_match('/([^\/]*).git$/', $this->seed->build, $m);
        $name = $m[1];
        
        if (file_exists($name)) {
            $this->output->write(PHP_EOL . '<error>Could not install package with the name ' . $name . 'because this directory already exists. Please delete and re-run.</error>' . PHP_EOL);
            die();
        }
        
        $this->terminal->run($this->git->clone($this->seed->build));
        
        
        $this->output->write('<info>Ok</info>' . PHP_EOL);
        
        $this->output->write('Attempting to unpack...');
        $this->terminal->run('mv ./' . $name . '/* ./');
        $this->output->write('<info>Ok</info>' . PHP_EOL);
        
        $this->output->write('Installing build packages. This may take a moment...');
        $this->terminal->run('npm install');
        $this->output->write('<info>Ok</info>' . PHP_EOL);
        
        
        $this->output->write('Attempting to configure build files...');
        
    }

    
}
