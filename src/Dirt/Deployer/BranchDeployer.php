<?php
namespace Dirt\Deployer;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Dirt\Configuration;
use Dirt\TemplateHandler;
use Dirt\Tools\LocalTerminal;
use Dirt\Tools\RemoteTerminal;
use Dirt\Tools\RemoteFileSystem;
use Dirt\Tools\GitBuilder;
use Dirt\Tools\MySQLBuilder;

class BranchDeployer extends Deployer
{
    private $databaseCredentials;
    
    private $stagingTerminal;
    
    private $terminal;
    
    private $git;
    
    private $branch;
    
    
    public function __construct() {
    
    }

    /**
     * Returns current environment name
     * @return string
     */
    public function getEnvironment()
    {
        return 'branch';
    }

    /**
     * Invokes the deployment process, pushing latest version to the repo
     */
    public function deploy()
    {
        
        $this->terminal = new LocalTerminal($this->project->getDirectory(), $this->output);
        $this->git = new GitBuilder();
        
        
        $this->output->writeln('Starting deployment process... ');
        
        $this->output->write('Getting current working branch... ');
        $this->getBranch();
        
        $this->output->write('Checking for changes in branch ' . $this->branch . '...');
        $this->checkDiffs();

        // Push all changes on master branch
        $this->output->write('Pushing changes to branch ' . $this->branch . '...');
        $this->push();
        
        $this->output->write('<info>Deployment to branch ' . $this->branch . ' successful.</info>' . PHP_EOL);
    }
    
    
    /**
    * Get current working branch
    **/
    protected function getBranch()
    {
        $branches = $this->terminal->run($this->git->branch());

        preg_match('/\*\s([\w]*)/', $branches, $matches);
        
        if (!empty($matches[1])) {
            $this->branch = $matches[1];
        }
        
        $this->output->write('<info>' . $this->branch . '</info>' . PHP_EOL);
    }
    
    
    protected function checkDiffs() {
        
        // Check if there is any local changes
        $status = $this->terminal->run($this->git->status());
        
        if (strpos($status, 'nothing to commit') === false)
        {
            
          $this->output->write('<info>Changes found</info>');
          $this->output->write(PHP_EOL);
          // Show diff
          $this->output->writeln($this->terminal->ignoreError()->run($this->git->diff()));            
            
          $message = $this->dialog->ask(
              $this->output,
              '<question>You have uncommitted changes, please provide a commit message:</question> '
          );

          $this->terminal->run($this->git->add('-A .'));
            
          $this->terminal->ignoreError()->run($this->git->commit($message));
            
        } else {
            $this->output->write('<info>Ok</info>' . PHP_EOL);
        }
    }
    
    protected function push() {
        $this->terminal->run($this->git->push('origin ' . $this->branch));
        $this->output->write('<info>Ok</info>' . PHP_EOL);
    }
    
    
    /**
    * Undeploy from branch
    **/
    public function undeploy()
    {
        
    }

}
