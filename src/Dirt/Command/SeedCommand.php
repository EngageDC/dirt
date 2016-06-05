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
    
    private $env;
    
    private $seed;
    private $teamSeed;
    
    private $buildRepo;
    private $hasBuild;
    private $themeRepo;
    private $hasTheme;
    private $pluginsRepo;
    private $hasPlugins;
    private $composerFile;
    private $hasComposer;

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
        
                
        $this->output->write('Detecting environment ... ');
        $this->detectEnvironment();
        
        $this->output->write('Checking for build files ...');
        if (!$this->project->hasSeed() && !$this->config->hasTeamSeed()) {
            $this->output->write('<error>None found. Exiting.' . PHP_EOL);
            $this->output->writeln('<comment>Please put seed information can be placed in either the Dirtfile or team configuation and then re-run "dirt seed."</comment>');
        } else {
            $this->getSeed();
        }
        
        //Start the process... env dependent
        if ($this->env === 'wordpress') {
            $this->doWordpress();
        } elseif ($this->env === 'laravel') {
            $this->doLaravel();
        }
        
    }
    
    
    /**
    * Controller for WordPress Seeder functions
    *
    * @param: none;
    *
    * @return: mixed (output responses)
    **/
    protected function doWordpress() {
        
        if ($this->hasTheme) {
            $this->output->write('Getting theme repo ...');
            $this->terminal->run('rm -rf public/wp-content/themes/*');
            $this->terminal->run('git clone ' . $this->themeRepo . ' public/wp-content/themes/' . $this->project->getName());
            $this->output->write('<info>Ok</info>' . PHP_EOL);
        }
        
        if ($this->hasPlugins) {
            $this->output->write('Getting plugins repo ...');
            $this->terminal->run('rm -rf public/wp-content/plugins');
            $this->terminal->run('git clone ' . $this->pluginsRepo . ' public/wp-content/plugins');
            $this->output->write('<info>Ok</info>' . PHP_EOL);
        }
        
    }
    
    
    
    /**
    * Controller for Laravel Seeder functions
    *
    * @param: none;
    *
    * @return: mixed (output responses)
    **/
    protected function doLaravel() {
        
        
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
    
    /**
    * Call appropriate seed functions by environment
    *
    * @param: none;
    *
    * @return none (populating build files)
    **/
    protected function getSeed() {
        $this->seed = $this->project->getSeed();
        $this->teamSeed = $this->config->getTeamSeed();
        
        
        if ($this->env === 'wordpress') {
            $this->getWpSeed();
        } elseif ($this->env === 'laravel ') {
            $this->getLaravelSeed();
        }
        
    }
    
    /**
    * Populate WordPress seed values
    *
    * @param: none;
    *
    * @return none (populating build files)
    **/
    protected function getWpSeed() {
        
        
        //check for build files
        if (!empty($this->seed->build)) {
            $this->hasBuild = true;
            $this->buildRepo = $this->seed->build;
        } elseif (!empty($this->teamSeed->wordpress->build)) {
            $this->hasBuild = true;
            $this->$buildRepo = $this->teamSeed->wordpress->build;
        } else {
            $this->hasBuild = false;
            $this->output->write(PHP_EOL . "<comment>No build repo found. Continuing...</comment" . PHP_EOL);
        }
        
        //check for theme files
        if (!empty($this->seed->theme)) {
            $this->hasTheme = true;
            $this->themeRepo = $this->seed->theme;
        } elseif (!empty($this->teamSeed->wordpress->theme)) {
            $this->hasTheme = true;
            $this->themeRepo = $this->teamSeed->wordpress->theme;
        } else {
            $this->hasTheme = false;
            $this->output->write(PHP_EOL . "<comment>No theme repo found. Continuing...</comment" . PHP_EOL);
        }
        
        // check for plugins files
        if (!empty($this->seed->plugins)) {
            $this->hasPlugins = true;
            $this->pluginRepo = $this->seed->plugins;
        } elseif (!empty($this->teamSeed->wordpress->plugins)) {
            $this->hasPlugins = true;
            $this->pluginsRepo = $this->teamSeed->wordpress->plugins;
        } else {
            $this->hasPlugins = false;
            $this->output->write(PHP_EOL . "<comment>No plugins repo found. Continuing...</comment" . PHP_EOL);
        }
        
        $this->output->write('<info>Build information loaded</info>' . PHP_EOL);
    }
    
    
    /**
    * Populate Laravel seed values
    *
    * @param: none;
    *
    * @return none (populating build files)
    **/
    protected function getLaravelSeed() {
        
    }

    /**
    * Detect the operating environment of the project
    *
    * @param: none;
    *
    * @return ouput and defining $this->env
    **/
    protected function detectEnvironment() {
        
        $isWordpress = $this->terminal->run('test -f public/wp-config.php && echo "true" || echo "false"');
        $isLaravel = $this->terminal->run('test -f artisan && echo "true" || echo "false"');
        
        if (strpos($isWordpress, "true") !== false) {
            $this->output->write('<info>WordPress</info>' . PHP_EOL);
            $this->env = 'wordpress';
        } elseif ( strpos($isLaravel, "true") !== false ) {
            $this->output->write('<info>Laravel</info>' . PHP_EOL);
            $this->env = 'laravel';
        }
    }
    
}
