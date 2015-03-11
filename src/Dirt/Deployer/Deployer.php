<?php
namespace Dirt\Deployer;

use Dirt\Configuration;

abstract class Deployer
{
    protected $input;
    protected $output;
    protected $dialog;
    protected $project;
    protected $config;
    protected $verbose = false;
    protected $yes = false;
    protected $no = false;

    public function setInput($input)
    {
        $this->input = $input;
    }

    public function setOutput($output)
    {
        $this->output = $output;
    }

    public function setDialog($dialog)
    {
        $this->dialog = $dialog;
    }

    public function setProject($project)
    {
        $this->project = $project;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    public function setYes($yes)
    {
        $this->yes = $yes;
    }

    public function setNo($no)
    {
        $this->no = $no;
    }
    
    abstract function getEnvironment();
    abstract function deploy();
    abstract function undeploy();
}
