<?php
namespace Dirt;

class Configuration {

    private $config = null;
    private $localConfigFilename = null;
    private $teamConfigFilename = null;

    public function __construct($localConfigFilename = null, $teamConfigFilename = null)
    {
        $this->localConfigFilename = $localConfigFilename ? $localConfigFilename : $_SERVER['HOME'] . '/.dirt';
        $this->teamConfigFilename = $teamConfigFilename ? $teamConfigFilename : __DIR__ . '/../../team/config.php';
        $this->load();

        return $this;
    }

    public function configurationExists()
    {
        return file_exists($this->localConfigFilename);
    }

    public function teamConfigurationExists()
    {
        return file_exists($this->teamConfigFilename);
    }

    public function load()
    {
        // Check if configuration file exists
        if (!$this->configurationExists()) {
            throw new \RuntimeException('Local dirt configuration file not found. Please create one and put it here: ' . $this->localConfigFilename);
        }

        $localConfig = require($this->localConfigFilename);

        // Do we need to use a team configuration?
        if ($this->teamConfigurationExists()) {
            $teamConfig = require($this->teamConfigFilename);

            // Local config overrides team config if necessary
            $this->config = (object)array_replace_recursive($teamConfig, $localConfig);
        } else {
            $this->config = $localConfig;
        }

        // Convert array to object
        // Yep, this is kind of silly
        $this->config = json_decode(json_encode($this->config));

        return $this;
    }

    public function __get($varName)
    {
        if (!isset($this->config->$varName)) {
            throw new \Exception('"' . $varName . '" does not exist in the configuration');
        }

        return $this->config->$varName;
    }
}