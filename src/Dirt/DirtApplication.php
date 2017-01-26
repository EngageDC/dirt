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
    private static $version = '1.4';

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
            $container->get('Dirt\Command\TransferUploadsCommand'),
            $container->get('Dirt\Command\BranchCommand'),
            $container->get('Dirt\Command\SeedCommand'),
        ));

        $d = new \DateTime;
        if ($d->format('m-d') == '04-01') {
            echo base64_decode('ICAgICAgICAgICAgIDBfDQogICAgICAgICAgICAgIFxgLiAgICAgX19fDQogICAgICAgICAgICAgICBcIFwgICAvIF9fPjANCiAgICAgICAgICAgL1wgIC8gIHwvJyAvIA0KICAgICAgICAgIC8gIFwvICAgYCAgLGAnLS0uDQogICAgICAgICAvIC8oX19fX19fX19fX18pXyBcDQogICAgICAgICB8LyAvLy4tLiAgIC4tLlxcIFwgXA0KICAgICAgICAgMCAvLyA6QCBfX18gQDogXFwgXC8NCiAgICAgICAgICAgKCBvIF4oX19fKV4gbyApIDANCiAgICAgICAgICAgIFwgXF9fX19fX18vIC8NCiAgICAgICAgL1wgICAnLl9fX19fX18uJy0tLg0KICAgICAgICBcIC98ICB8PF9fX19fPiAgICB8DQogICAgICAgICBcIFxfX3w8X19fX18+X19fXy98X18NCiAgICAgICAgICBcX19fXzxfX19fXz5fX19fX19fLw0KICAgICAgICAgICAgICB8PF9fX19fPiAgICB8DQogICAgICAgICAgICAgIHw8X19fX18+ICAgIHwNCiAgICAgICAgICAgICAgOjxfX19fXz5fX19fOg0KICAgICAgICAgICAgIC8gPF9fX19fPiAgIC98DQogICAgICAgICAgICAvICA8X19fX18+ICAvIHwNCiAgICAgICAgICAgL19fX19fX19fX19fLyAgfA0KICAgICAgICAgICB8ICAgICAgICAgICB8IF98X18NCiAgICAgICAgICAgfCAgICAgICAgICAgfCAtLS18fF8NCiAgICAgICAgICAgfCAgIHxMXC98LyAgfCAgfCBbX19dDQogICAgICAgICAgIHwgIFx8fHxcfFwgIHwgIC8NCiAgICAgICAgICAgfCAgICAgICAgICAgfCAvDQogICAgICAgICAgIHxfX19fX19fX19fX3wvDQoNCkhhcHB5IEFwcmlsIEZvb2xzJyBEYXkgZnJvbSBDb2RlbW9ua2V5IQ==');
        }

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
