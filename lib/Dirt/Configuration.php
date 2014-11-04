<?php
namespace Dirt;

class Configuration {

    private $config = null;
    private $configFilename = null;

    public function __construct()
    {
        $this->configFilename = $_SERVER['HOME'] . '/.dirt';
        $this->load();

        return $this;
    }

    public function configurationExists()
    {
        return file_exists($this->configFilename);
    }

    public function load()
    {
        // Check if configuration file exists
        if (!$this->configurationExists()) {
            throw new \RuntimeException('Dirt configuration file not found, please run "dirt setup" first.');
        }

        $localConfig = require($this->configFilename);
        $teamConfig = require(__DIR__ . '/../../team/config.php');

        // Local config overrides team config if necessary
        $this->config = array_replace_recursive($teamConfig, $localConfig);

        return $this;
    }

    public function __get($varName)
    {
        return $this->config->$varName;
    }
}