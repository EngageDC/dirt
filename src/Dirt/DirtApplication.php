<?php
namespace Dirt;
 
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DirtApplication extends Application {

    public function __construct() {
        parent::__construct('dirt [Done In Record Time]', '1.0.0');

        $builder = new \DI\ContainerBuilder();
        $container = $builder->build();
 
        $this->addCommands(array(
            $container->get('Dirt\Command\OpenCommand'),
            $container->get('Dirt\Command\SetupCommand'),
            $container->get('Dirt\Command\CreateCommand'),
            $container->get('Dirt\Command\DeployCommand'),
            $container->get('Dirt\Command\ApplyCommand'),
            $container->get('Dirt\Command\DatabaseDumpCommand'),
            $container->get('Dirt\Command\UpdateCommand')
        ));
    }

    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),

            new InputOption('--help',           '-h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption('--quiet',          '-q', InputOption::VALUE_NONE, 'Do not output any message.'),
            new InputOption('--verbose',        '-v', InputOption::VALUE_NONE, 'Increase verbosity of messages.'),
            new InputOption('--version',        '-V', InputOption::VALUE_NONE, 'Display this application version.'),
        ));
    }
}