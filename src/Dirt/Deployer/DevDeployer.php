<?php
namespace Dirt\Deployer;

use Symfony\Component\Process\Process;
use Dirt\Configuration;

class DevDeployer extends Deployer
{
    private $ssh;

    /**
     * Returns current environment name
     * @return string
     */
    public function getEnvironment()
    {
        return 'dev';
    }

    /**
     * Invokes the deployment process, pushing latest version to the server
     */
    public function deploy()
    {

    }

    /**
     * Removes all configuration, files and database credentials from the server
     */
    public function undeploy()
    {

    }

}