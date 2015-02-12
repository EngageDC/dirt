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
use Dirt\Repositories\VersionControlRepositoryGitLab;
use Dirt\Repositories\VersionControlRepositoryGitHub;

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
            ->addOption(
                'skip-repository',
                null,
                InputOption::VALUE_NONE,
                'If set, the remote GitHub/GitLab repository will not be created'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Move input and output to class scope
        $this->input = $input;
        $this->output = $output;

        // Define the new project's metadata
        $this->project = new Project();
        $this->project->setConfig($this->config);
        $this->project->setName($input->getArgument('name'));
        $this->project->generateProperties();
        $this->project->setDirectory(getcwd() . '/' . $this->project->getName(true));
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

        // Only create repository if skip-repository flag hasn't been set
        if (!$input->getOption('skip-repository')) {
            $this->createRepository();
        }

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
                'git commit -m "Added '. $this->project->getFramework()->getName(false) .'"'
            );

            if (!$input->getOption('skip-repository')) {
                $commands[] = 'git push origin master';
            }

            foreach ($commands as $command) {
                $process->setCommandLine($command);
                $process->run();
                $this->output->write('.');
                if (!$process->isSuccessful()) {
                    $message = 'Error: Could not run "'. $command .'", git returned: ' . $process->getErrorOutput();
                    $this->output->writeln('<error>'. $message . '</error>');
                    throw new \RuntimeException($message);
                }
            }
            $this->output->writeln('<info>OK</info>');
        } else {
            // Just create the public directory
            mkdir($this->project->getDirectory() . '/public');
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
            $versionControlRepository = ($this->config->scm->type == 'gitlab') ?
                new VersionControlRepositoryGitLab($this->config) : new VersionControlRepositoryGitHub($this->config);
            $versionControlRepository->create($this->project);

            $this->output->writeln('<info>OK</info>');
        } catch (\Exception $e) {
            $message = 'Error: '. $e->getMessage();
            $this->output->writeln('<error>'. $message .'</error>');
            throw new \RuntimeException($message);
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
            $message = 'Error: Could not create directory';
            $this->output->writeln('<error>' . $message . '</error>');
            throw new \RuntimeException($message);
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
            'git commit -m "Initial commit, added README, gitignore, Dirtfile and Vagrantfile"'
        );

        if (!$this->input->getOption('skip-repository')) {
            $commands[] = 'git remote add origin ' . $this->project->getRepositoryUrl();
            $commands[] = 'git push -u origin master';
        }

        foreach ($commands as $command) {
            $process->setCommandLine($command);
            $process->run();
            if (!$process->isSuccessful()) {
                $message = 'Error: Could not run "'. $command .'", git returned: ' . $process->getErrorOutput();
                $this->output->writeln('<error>'. $message . '</error>');
                throw new \RuntimeException($message);
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
