<?php
namespace Dirt;
 
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DirtApplication extends Application {

    private static $logo =   '
       ___      __ 
  ____/ (_)____/ /_
 / __  / / ___/ __/
/ /_/ / / /  / /_  
\__,_/_/_/   \__/  

';

    private static $name = 'dirt [Done In Record Time]';
    private static $version = '1.2';

    public function __construct() {


        parent::__construct(self::$name, self::$version);

        $builder = new \DI\ContainerBuilder();
        $container = $builder->build();
 
        $this->addCommands(array(
            $container->get('Dirt\Command\OpenCommand'),
            $container->get('Dirt\Command\CreateCommand'),
            $container->get('Dirt\Command\DeployCommand'),
            $container->get('Dirt\Command\ApplyCommand'),
            $container->get('Dirt\Command\UpdateCommand'),
            $container->get('Dirt\Command\TransferCommand'),
            $container->get('Dirt\Command\TransferDatabaseCommand'),
            $container->get('Dirt\Command\TransferUploadsCommand')
        ));
    }

    public function getHelp()
    {
        return self::$logo . parent::getHelp();
    }

    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),

            new InputOption('--help',           '-h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption('--quiet',          '-q', InputOption::VALUE_NONE, 'Do not output any message.'),
            new InputOption('--verbose',        '-v|vv|vvv', InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug'),
            new InputOption('--version',        '-V', InputOption::VALUE_NONE, 'Display this application version.'),
        ));
    }
}
