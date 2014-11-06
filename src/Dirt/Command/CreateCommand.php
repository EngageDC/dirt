<?php
namespace Dirt\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Dirt\Project;
use Dirt\Framework\Framework;
use Dirt\TemplateHandler;

class CreateCommand extends Command
{
    private $config;
    private $project;
    
    private $input;
    private $output;

    public function __construct(\Dirt\Configuration $configuration) {
        parent::__construct();

        $this->config = $configuration;
    }

    protected function configure()
    {
        $this
            ->setName('create')
            ->setDescription('Create a new project')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The simple name of the project'
            )
            ->addOption(
               'framework',
               'f',
               InputOption::VALUE_REQUIRED,
               'Optionally specify a framework to initialize the project with'
            )
            ->addOption(
               'description',
               'd',
               InputOption::VALUE_REQUIRED,
               'Optionally specify a project description'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Move input and output to class scope
        $this->input = $input;
        $this->output = $output;

        // Define the new project's metadata
        $this->project = new Project($input->getArgument('name'), $this->config);
        $this->project->setDescription($input->getOption('description'));

        // Check if directory already exists
        if (file_exists($this->project->getDirectory())) {
            throw new \RuntimeException('Directory "' . $this->project->getDirectory() . '" already exists!');
        }

        // Validate framework
        $frameworkName = $input->getOption('framework');
        
        if ($frameworkName) {
            $frameworkName = strtolower($frameworkName);

            foreach (Framework::availableFrameworks() as $framework) {
                if (in_array($frameworkName, $framework->getShortcuts())) {
                    $this->project->setFramework($framework);
                    break;
                }
            }

            if ($this->project->getFramework() === FALSE) {
                throw new \InvalidArgumentException('Invalid framework ' . $frameworkName . ', valid frameworks are: ' . implode(' ', Framework::validFrameworkShortcuts()));
            }
        }

        // Perform project creation actions
        $output->writeln('Creating new project in ' . $this->project->getDirectory());
        $this->createRepository();
        $this->initializeProjectDirectory();
        
        // Install framework if needed
        if ($this->project->getFramework() !== FALSE) {
            $this->output->writeln('Installing ' . $this->project->getFramework()->getName(false) . '...');

            $o = $this->output;
            $this->project->getFramework()->install($this->project, function ($message) use ($o) {
                $o->write($message);
            });
            $this->project->getFramework()->configureEnvironment('dev', $this->project);

            // Push this change to the git remote
            $this->output->write("\t" . 'Pushing changes to git remote.');
            $process = new Process(null, $this->project->getDirectory());
            $process->setTimeout(3600);

            $commands = array(
                'git add -A .',
                'git commit -m "Added '. $this->project->getFramework()->getName(false) .'"',
                'git push origin master'
            );

            foreach ($commands as $command) {
                $process->setCommandLine($command);
                $process->run();
                $this->output->write('.');
                if (!$process->isSuccessful()) {
                    $this->output->writeln('<error>Error: Could not run "'. $command .'", git returned: ' . $process->getErrorOutput() . '</error>');
                    exit(1);
                }
            }
            $this->output->writeln('<info>OK</info>');
            
        }

        // Output final project information
        $this->showProjectInfo();
    }

    /**
     * Creates a new repository with the given name and optional description
     */
    private function createRepository()
    {
        $this->output->write('Creating repository... ');

        try {
            if ($this->config->scm->type == 'github') {
                $githubClient = new \Github\Client();
                // Set authentication info
                $githubClient->authenticate(
                        $this->config->scm->username,
                        $this->config->scm->password,
                        \Github\Client::AUTH_HTTP_PASSWORD
                );

                // Create repository
                $repo = $githubClient->api('repo')->create(
                    $this->project->getName(),
                    $this->project->getDescription(),
                    $this->project->getStagingUrl(),
                    false,
                    $this->config->scm->organization
                );

                // Save the SSH repository URL
                $this->project->setRepositoryUrl($repo['ssh_url']);                
            } else {
                $gitlabClient = new \Gitlab\Client($this->config->scm->domain . '/api/v3/');
                // Set authentication info
                $gitlabClient->authenticate($this->config->scm->private_token, \Gitlab\Client::AUTH_URL_TOKEN);

                // Create repository
                $project = NULL;
                try {
                    $project = $gitlabClient->api('projects')->create(
                        $this->project->getName(),
                        array(
                            'description' => $this->project->getDescription()
                        )
                    );
                } catch (\Exception $e) {
                    // Gitlab throws a 404 error when a project with the name already exists in the user namespace
                    if ($e->getCode() == 404) {
                        // The Gitlab API doesn't seem to support deleting projects, so we'll have to keep it in the user namespace
                        throw new \Exception('It looks like a project with this name already exists in your user namespace. Please verify and try again.');
                    } else {
                        throw new \Exception($e);
                    }
                }

                // Move to group if necessary
                if (isset($this->config->scm->group_id) && filter_var($this->config->scm->group_id, FILTER_VALIDATE_INT) !== FALSE) {
                    sleep(4); // Wait a few seconds so we know that the project is fully available (gitlab have had problems throwing random 500 errors here)

                    try {
                        $response = $gitlabClient->api('groups')->transfer($this->config->scm->group_id, $project['id']);
                    } catch (\Exception $e) {
                        // Gitlab throws a 500 error when a project with the name already exists in the group
                        if ($e->getCode() == 500) {
                            // The Gitlab API doesn't seem to support deleting projects, so we'll have to keep it in the user namespace
                            throw new \Exception('It looks like a project with this name already exists in the target group. The project have been kept in the user namespace. Please verify and try again.');
                        } else {
                            throw new \Exception($e);
                        }
                    }
                    // Refresh project information
                    $project = $gitlabClient->api('projects')->show($project['id']);
                }

                // Save the SSH repository URL
                $this->project->setRepositoryUrl($project['ssh_url_to_repo']);
            }

            $this->output->writeln('<info>OK</info>');
        } catch (\Exception $e) {
            $this->output->writeln('<error>Error: '. $e->getMessage() .'</error>');
            exit(1);
        }
    }

    /**
     * Creates project directory and initial default files
     */
    private function initializeProjectDirectory()
    {
        $this->output->write('Adding initial files... ');

        // Create directory
        if (!@mkdir($this->project->getDirectory())) {
            $this->output->writeln('<error>Error: Could not create directory</error>');
            exit(1);
        }

        // Add template files
        $templateHandler = new TemplateHandler();
        $templateHandler->setProject($this->project);

        $templateHandler->writeTemplate('README.md');
        $templateHandler->writeTemplate('gitignore');
        $templateHandler->writeTemplate('Vagrantfile');

        // Add Dirtfile
        $this->project->save();
        
        // Initialize git for working directory
        $process = new Process(null, $this->project->getDirectory());
        $process->setTimeout(3600);

        $commands = array(
            'git init',
            'git add -A .',
            'git commit -m "Initial commit, added README, gitignore, Dirtfile and Vagrantfile"',
            'git remote add origin ' . $this->project->getRepositoryUrl(),
            'git push -u origin master'
        );

        foreach ($commands as $command) {
            $process->setCommandLine($command);
            $process->run();
            if (!$process->isSuccessful()) {
                $this->output->writeln('<error>Error: Could not run "'. $command .'", git returned: ' . $process->getErrorOutput() . '</error>');
                exit(1);
            }
        }

        $this->output->writeln('<info>OK</info>');
    }

    /**
     * Outputs project information to the console and assists with running vagrant up
     */
    private function showProjectInfo()
    {
        // URLs
        $this->output->writeln('');
        $this->output->writeln('<info>'. $this->project->getName(false) .'</info> has now been created.');
        $this->output->writeln('<comment>Development:</comment> ' . $this->project->getDevUrl());
        $this->output->writeln('<comment>Staging:</comment> ' . $this->project->getStagingUrl());
        $this->output->writeln('<comment>Production:</comment> N/A');

        // Database credentials
        $databaseCredentials = $this->project->getDatabaseCredentials('dev');
        $this->output->writeln('');
        $this->output->writeln('<info>Development MySQL credentials</info>');
        $this->output->writeln('Username: ' . $databaseCredentials['username']);
        $this->output->writeln('Password: ' . $databaseCredentials['password']);
        $this->output->writeln('Database: ' . $databaseCredentials['database']);

        $databaseCredentials = $this->project->getDatabaseCredentials('staging');
        $this->output->writeln('');
        $this->output->writeln('<info>Staging MySQL credentials</info>');
        $this->output->writeln('Username: ' . $databaseCredentials['username']);
        $this->output->writeln('Password: ' . $databaseCredentials['password']);
        $this->output->writeln('Database: ' . $databaseCredentials['database']);
        $this->output->writeln('');

        // We're done!
        $this->output->writeln('<info>You can now run "cd '. $this->project->getName() .' && vagrant up"</info>');
    }
}