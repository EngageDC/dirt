<?php
namespace Dirt\Deployer;

use Symfony\Component\Process\Process;
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

    /**
     * Returns current environment name
     * @return string
     */
    public function getEnvironment()
    {
        return 'branch';
    }

    /**
     * Invokes the deployment process, pushing latest version to the staging server
     */
    public function deploy()
    {
        $this->output->write('Deploy... ' . PHP_EOL);
    }

}
