<?php
namespace Dirt\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Dirt\Configuration;

class SetupCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('setup')
            ->setDescription('Creates a dirt configuration and verifies that required dependencies are installed')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>dirt setup is currently unavailable</info>');
        return;

        // Introduction text
        $output->writeln('<info>Let\'s get started with dirt!</info>');
        $output->writeln('<info>This will set up your dirt installation and create a .dirt configuration file in your home directory.</info>');
        $output->writeln('');

        $dialog = $this->getHelperSet()->get('dialog');

        // Initialize configuration
        $config = new Configuration();
        
        if ($config->configurationExists()) {
            if (!$dialog->askConfirmation(
                    $output,
                    '<question>This will overwrite the existing configuration, do you want to continue?</question> ',
                    false
                ))
            {
                return;
            }
        }

        // SCM Provider
        $output->writeln('<comment>SCM Provider</comment>');
        $scmProvider = $dialog->select(
            $output,
            'Where is your source code currently hosted?',
            array(
                'github' => 'Private or public repository on GitHub',
                'gitlab' => 'Self hosted or Gitlab Cloud'
            )
        );
        $output->writeln('');

        $scmCredentials = array('type' => $scmProvider);

        if ($scmProvider == 'github') {
            // GitHub
            $output->writeln('<comment>GitHub Account</comment>');
            do
            {
                $authenticationFailed = false;
                $githubClient = new \Github\Client();

                $githubUsername = $dialog->ask(
                    $output,
                    'Please enter your GitHub username: '
                );

                $githubPassword = $dialog->askHiddenResponse(
                    $output,
                    'Please enter your GitHub password: '
                );

                $githubOrganization = $dialog->ask(
                    $output,
                    'Please enter your GitHub organization (if applicable): '
                );

                $output->write('Verifying Github credentials... ');

                try {
                    $githubClient->authenticate($githubUsername, $githubPassword, \Github\Client::AUTH_HTTP_PASSWORD);
                    $repositories = $githubClient->api('current_user')->repositories();

                    $output->writeln('<info>OK</info>');
                    $authenticationFailed = false;
                } catch (\Exception $e) {
                    $output->writeln('<error>Error: '. $e->getMessage() .'</error>');
                    $authenticationFailed = true;
                }
            }
            while ($authenticationFailed);
            $output->writeln('');

            $scmCredentials['username'] = $githubUsername;
            $scmCredentials['password'] = $githubPassword;
            $scmCredentials['organization'] = $githubOrganization;
        } else {
            // Gitlab
            $output->writeln('<comment>Gitlab credentials</comment>');
            do
            {
                $authenticationFailed = false;

                $defaultGitlabDomain = isset($gitlabDomain) ? $gitlabDomain : 'https://git.mysite.com';
                $gitlabDomain = $dialog->ask(
                    $output,
                    'Please enter Gitlab server URL ['. $defaultGitlabDomain .']: ',
                    $defaultGitlabDomain
                );

                $gitlabToken = $dialog->askHiddenResponse(
                    $output,
                    'Please enter your Gitlab private token: '
                );

                $output->write('Verifying Gitlab credentials... ');

                $gitlabClient = new \Gitlab\Client($gitlabDomain . '/api/v3/');
                try {
                    $gitlabClient->authenticate($gitlabToken, \Gitlab\Client::AUTH_URL_TOKEN);
                    $repositories = $gitlabClient->api('projects')->all();

                    if (!is_array($repositories)) {
                        throw new \RuntimeException('Could not query projects. Please make sure that domain and credentials are correct.');
                    }

                    $output->writeln('<info>OK</info>');
                    $authenticationFailed = false;
                } catch (\Exception $e) {
                    $output->writeln('<error>Error: '. $e->getMessage() .'</error>');
                    $authenticationFailed = true;
                }

                // Select default project group
                $gitlabClient->authenticate($gitlabToken, \Gitlab\Client::AUTH_URL_TOKEN);

                // Sorry, if you have more than 30 groups you must update the configuration manually
                $groups = $gitlabClient->api('groups')->all(1, 30);
                
                // Prepare options
                $groupOptions = array();
                $groupOptions[0] = 'None, use my user namespace';
                foreach ($groups as $group) {
                    $groupOptions[$group['id']] = $group['name'];
                }

                $gitlabGroup = $dialog->select(
                    $output,
                    'Select default project group',
                    $groupOptions
                );
            }
            while ($authenticationFailed);
            $output->writeln('');

            $scmCredentials['domain'] = $gitlabDomain;
            $scmCredentials['private_token'] = $gitlabToken;  
            $scmCredentials['group_id'] = intval($gitlabGroup);
            if ($scmCredentials['group_id'] <= 0) {
                 $scmCredentials['group_id'] = '';
            }
        }

        $environments = array();

        if ($dialog->askConfirmation(
                $output,
                '<question>Do you want to configure authenticating with a staging server now? (Recommended)</question> ',
                false
            ))
        {
            // Staging server
            define('NET_SSH2_LOGGING', 2);
            $output->writeln('<comment>Staging Server</comment>');
            do
            {
                $authenticationFailed = false;
                
                $defaultStagingHostname = 'staging.mysite.com';
                $stagingHostname = $dialog->ask(
                    $output,
                    'Please enter hostname ['. $defaultStagingHostname .']: ',
                    $defaultStagingHostname
                );

                $defaultStagingPort = '22';
                $stagingPort = $dialog->ask(
                    $output,
                    'Please enter SSH port ['. $defaultStagingPort .']: ',
                    $defaultStagingPort
                );

                $stagingUsername = $dialog->ask(
                    $output,
                    'Please enter username: '
                );

                $defaultKeyFile = $_SERVER['HOME'] . '/.ssh/id_rsa';
                $stagingKeyFile = $dialog->ask(
                    $output,
                    'Please enter keyfile ['. $defaultKeyFile .']: ',
                    $defaultKeyFile
                );

                $output->write('Verifying staging credentials... ');
                $ssh = new \Net_SSH2($stagingHostname);
                $key = new \Crypt_RSA();
                $key->loadKey(file_get_contents($stagingKeyFile));
                if (!$ssh->login($stagingUsername, $key)) {
                    $output->writeln('<error>Error: Authentication failed</error>');
                    print_r($ssh->getLog());
                    $authenticationFailed = true;
                } else {
                    $output->writeln('<info>OK</info>');
                    $authenticationFailed = false;
                }
            }
            while ($authenticationFailed);
            $output->writeln('');

            // Staging server MySQL root password
            $output->writeln('<comment>Staging Server: MySQL</comment>');
            $stagingMySQLHostname = $dialog->ask(
                $output,
                'Please enter MySQL hostname [localhost]: ',
                'localhost'
            );

            $stagingMySQLUsername = $dialog->ask(
                $output,
                'Please enter MySQL username: '
            );

            $stagingMySQLPassword = $dialog->askHiddenResponse(
                $output,
                'Please enter MySQL password: '
            );
            $output->writeln('');

            $environments['staging'] = array(
                'hostname' => $stagingHostname,
                'port' => $stagingPort,
                'username' => $stagingUsername,
                'keyfile' => $stagingKeyFile,
                'mysql' => array(
                    'hostname' => $stagingMySQLHostname,
                    'username' => $stagingMySQLUsername,
                    'password' => $stagingMySQLPassword,
                )
            );
        }

        if ($dialog->askConfirmation(
                $output,
                '<question>Do you want to configure authenticating with a production server now?</question> ',
                false
            ))
        {
            // Production server
            $output->writeln('<comment>Production Server</comment>');
            do
            {
                $authenticationFailed = false;
                
                $defaultProductionHostname = 'mysite.com';
                $productionHostname = $dialog->ask(
                    $output,
                    'Please enter hostname ['. $defaultProductionHostname .']: ',
                    $defaultProductionHostname
                );

                $defaultProductionPort = '22';
                $productionPort = $dialog->ask(
                    $output,
                    'Please enter SSH port ['. $defaultProductionPort .']: ',
                    $defaultProductionPort
                );

                $productionUsername = $dialog->ask(
                    $output,
                    'Please enter username: '
                );

                $defaultKeyFile = $_SERVER['HOME'] . '/.ssh/id_rsa';
                $productionKeyFile = $dialog->ask(
                    $output,
                    'Please enter keyfile ['. $defaultKeyFile .']: ',
                    $defaultKeyFile
                );

                $output->write('Verifying production credentials... ');
                $ssh = new \Net_SSH2($stagingHostname);
                $key = new \Crypt_RSA();
                $key->loadKey(file_get_contents($productionKeyFile));
                if (!$ssh->login($stagingUsername, $key)) {
                    $output->writeln('<error>Error: Authentication failed</error>');
                    print_r($ssh->getLog());
                    $authenticationFailed = true;
                } else {
                    $output->writeln('<info>OK</info>');
                    $authenticationFailed = false;
                }
            }
            while ($authenticationFailed);
            $output->writeln('');

            $environments['production'] = array(
                'hostname' => $productionHostname,
                'port' => $productionPort,
                'username' => $productionUsername,
                'keyfile' => $productionKeyFile
            );
        }

        // Store config file
        $config->save(array(
            'environments' => $environments,
            'scm' => $scmCredentials
        ));
    }
}