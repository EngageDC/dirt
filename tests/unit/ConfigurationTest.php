<?php
use Dirt\Project;
use Dirt\Configuration;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    
    /**
     * Premise: A local config change should overwrite a config entry from the team config
     */
    public function testOverwrite() {
        // Create a new configuration object
        $config = new Configuration(__DIR__ . '/../stubs/localconfig.txt', __DIR__ . '/../stubs/teamconfig.txt');

        // This should be .local rather than .team
        $this->assertEquals('.local', $config->environments->dev->domain_suffix);
    }

}
