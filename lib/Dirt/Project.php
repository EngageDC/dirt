<?php
namespace Dirt;

use Symfony\Component\Process\Process;
use Dirt\Framework\Framework;

class Project {

    private $projectNameSimple;
    private $projectNameFull;
    private $projectDescription;
    private $directory;
    private $ipAddress = null;
    private $productionDirectory = null;
    
    private $repositoryUrl;

    private $projectFramework = null;

    private $devUrl;
    private $stagingUrl;
    private $productionUrl;

    private $databaseCredentials = array();

    /**
    * @Inject
    * @var Dirt\Configuration
    */
    private $config;

    public function __construct($projectName, $projectNameFull = null, $databaseCredentials = null)
    {
        if (!is_null($projectNameFull)) {
            $this->projectNameSimple = $projectName;
            $this->projectNameFull = $projectNameFull;
            $this->directory = getcwd() . '/' . $this->projectNameSimple;
        } else {
            $this->setName($projectName);
        }

        if (!is_null($databaseCredentials)) {
            $this->databaseCredentials = $databaseCredentials;
        }

        // Generate ip address if necessary
        if (is_null($this->ipAddress)) {
            $this->ipAddress = $this->generateIpAddress();
        }
    }

    public static function fromDirtfile($filename)
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException('Dirtfile does not exist: ' . $filename);
        }

        $projectData = json_decode(file_get_contents($filename));

        if (!$projectData) {
            throw new \RuntimeException('Invalid file format, could not read Dirtfile');
        }

        $project = new Project($projectData->name, $projectData->name_full, (array)$projectData->database);
        $project->setDevUrl($projectData->urls->dev);
        $project->setStagingUrl($projectData->urls->staging);
        $project->setProductionUrl($projectData->urls->production);
        $project->setDirectory(dirname($filename));

        if (isset($projectData->production_directory)) {
            $project->setProductionDirectory($projectData->production_directory);
        }

        if (isset($projectData->ipaddress)) {
            $project->setIpAddress($projectData->ipaddress);
        }

        if (isset($projectData->framework) && !empty($projectData->framework)) {
            $project->setFramework(Framework::fromName($projectData->framework));
        }

        $project->parseGitConfig();

        return $project;
    }

    /**
     * Returns the project's name
     * @param bool $simple Should it be in the simple form?
     * @return string
     */
    public function getName($simple = true)
    {
        return $simple ? $this->projectNameSimple : $this->projectNameFull;
    }

    /**
     * Set's the project name
     * @param type $description 
     */
    public function setName($projectName)
    {
        $this->projectNameFull = $projectName;

        $simpleName = str_replace(' ', '-', $this->projectNameFull);
        $this->projectNameSimple = trim(preg_replace('/[^a-zA-Z0-9-]+/', '', $simpleName), '-');

        $this->devUrl = strtolower($this->projectNameSimple) . $this->config->environments->dev->domain_suffix;
        $this->stagingUrl = strtolower($this->projectNameSimple) . $this->config->environments->staging->domain_suffix;
        $this->productionUrl = strtolower($this->projectNameSimple) . $this->config->environments->production->domain_suffix;
        
        $this->directory = getcwd() . '/' . $this->projectNameSimple;
    }

    /**
     * Return's the project description
     * @return string
     */
    public function getDescription()
    {
        return $this->projectDescription;
    }

    /**
     * Set's the project description
     * @param string $description 
     */
    public function setDescription($description)
    {
        $this->projectDescription = $description;
    }

    /**
     * Parses the .git/config file to extract values for
     * the project such as the remote origin URL
     */
    public function parseGitConfig()
    {
        $configFilename = $this->getDirectory() . '/.git/config';
        $config = parse_ini_file($configFilename, TRUE);

        if ($config === FALSE) {
            throw new \RuntimeException('Could not parse ' . $configFilename);
        }

        if (!isset($config['remote origin']) || !isset($config['remote origin']['url'])) {
            throw new \RuntimeException('Could not find git remote URL');
        }

        $this->setRepositoryUrl($config['remote origin']['url']);
    }

    /**
     * Return's the project repository url
     * @return string
     */
    public function getRepositoryUrl()
    {
        return $this->repositoryUrl;
    }

    /**
     * Set's the project repository url
     * @param string $url 
     */
    public function setRepositoryUrl($url)
    {
        $this->repositoryUrl = $url;
    }

    /**
     * Return's the project framework
     * @return Framework
     */
    public function getFramework()
    {
        return (!isset($this->framework) || is_null($this->framework)) ? FALSE : $this->framework;
    }

    /**
     * Set's the project framework
     * @param Framework $projectFramework 
     */
    public function setFramework($projectFramework)
    {
        $this->framework = $projectFramework;
    }

    /**
     * Returns the full local dev URL for a given project name
     * @param bool $includeProtocol 
     * @return string
     */
    public function getDevUrl($includeProtocol = true)
    {
        return ($includeProtocol ? 'http://' : '') . $this->devUrl;
    }

    /**
     * Set's the development URL (without protocol)
     * @param string $url 
     */
    public function setDevUrl($url)
    {
        $this->devUrl = $url;
    }

    /**
     * Returns the full staging URL for a given project name
     * @return string
     */
    public function getStagingUrl($includeProtocol = true)
    {
        return ($includeProtocol ? 'http://' : '') . $this->stagingUrl;
    }

    /**
     * Set's the staging URL (without protocol)
     * @param string $url 
     */
    public function setStagingUrl($url)
    {
        $this->stagingUrl = $url;
    }

    /**
     * Returns the full production URL for a given project name
     * @return string
     */
    public function getProductionUrl($includeProtocol = true)
    {
        return ($includeProtocol ? 'http://' : '') . $this->productionUrl;
    }

    /**
     * Set's the production URL (without protocol)
     * @param string $url 
     */
    public function setProductionUrl($url)
    {
        $this->productionUrl = $url;
    }

    /**
     * Returns full path to local working directory
     * @return string
     */
    public function getProductionDirectory()
    {
        if (is_null($this->productionDirectory) || empty($this->productionDirectory)) {
            return '/var/sites/shared_hosting/' . $this->getProductionUrl(false);
        }

        return $this->productionDirectory;
    }

    /**
     * Set's the full path to the remote production directory
     * @param string $productionDirectory 
     */
    public function setProductionDirectory($productionDirectory)
    {
        $this->productionDirectory = $productionDirectory;
    }

    /**
     * Returns full path to local working directory
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Set's the full path to the local working directory
     * @param type $directory 
     */
    public function setDirectory($directory)
    {
        $this->directory = $directory;
    }

    /**
     * Returns the local ip address for the development environment
     * @return string
     */
    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    /**
     * Set's the local ip address for the development environment
     * @param type $ipAddress 
     */
    public function setIpAddress($ipAddress)
    {
        $this->ipAddress = $ipAddress;
    }

    /**
     * Generates a random ipv4 address
     * @return string
     */
    private function generateIpAddress()
    {
        return '172.' . mt_rand(16, 31) . '.' . mt_rand(0, 255) . '.' . mt_rand(2, 255);
    }

    /**
     * Returns array of database credentials for a given environment
     * @param string $environment 
     * @return array
     */
    public function getDatabaseCredentials($environment)
    {
        if (!isset($this->databaseCredentials[$environment])) {
            $this->databaseCredentials[$environment] = $this->generateDatabaseCredentials($environment);
        }

        $credentials = (array)$this->databaseCredentials[$environment];

        // Set default value
        if (!isset($credentials['hostname'])) {
            $credentials['hostname'] = 'localhost';
        }

        return $credentials;
    }

    /**
     * Generates and returns a set of database credentials for a given environment
     * @param string $environment 
     * @return array
     */
    public function generateDatabaseCredentials($environment)
    {
        $credentials = array();

        // Define database credentials
        // MySQL has a 16-character limit for usernames
        $credentials['username'] = strtolower(str_replace('-', '_', $this->getName()));
        if (strlen($credentials['username']) > 16) {
            $credentials['username'] = trim(substr($credentials['username'], 0, 16), '_');
        }

        $credentials['password'] = $this->generatePassword(12);
        $credentials['database'] = $credentials['username'];
        $credentials['hostname'] = 'localhost';

        return $credentials;
    }

    /**
     * Returns dev SSH credentials from local VM, using Vagrant
     * @return array
     */
    public function getDevelopmentServer()
    {
        $credentials = array();

        $process = new Process('vagrant ssh-config', $this->getDirectory());
        $process->run(function ($type, $buffer) use (&$credentials) {

            $lines = explode(PHP_EOL, $buffer);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;

                list($key, $value) = explode(' ', $line);

                $credentials[$key] = trim($value, '"');
            }
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Could not get dev SSH credentials, make sure local virtual machine is running by executing "vagrant up"');
        }

        return (object)array(
            'hostname' => $credentials['HostName'],
            'port' => $credentials['Port'],
            'keyfile' => $credentials['IdentityFile'],
            'username' => $credentials['User']
        );
    }

    /**
     * Returns a JSON representation of the project
     * @return string
     */
    public function asJson()
    {
        $projectData = array(
            'name' => $this->getName(),
            'name_full' => $this->getName(false),
            'production_directory' => $this->getProductionDirectory(),
            'ipaddress' => $this->getIpAddress(),
            'framework' => ($this->getFramework() !== FALSE) ? $this->getFramework()->getName() : null,
            'urls' => array(
                'dev' => $this->getDevUrl(false),
                'staging' => $this->getStagingUrl(false),
                'production' => $this->getProductionUrl(false),
            ),
            'database' => array(
                'dev' => $this->getDatabaseCredentials('dev'),
                'staging' => $this->getDatabaseCredentials('staging'),
                'production' => $this->getDatabaseCredentials('production'),
            )
        );

        return json_encode($projectData, JSON_PRETTY_PRINT);    
    }

    /**
     * Save Dirtfile.json for the project
     */
    public function save()
    {
        file_put_contents($this->getDirectory() . '/Dirtfile.json', $this->asJson());
    }

    /**
     * Generates a random string of a given length
     * @param type $length 
     * @return string
     */
    private function generatePassword($length = 11) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $count = mb_strlen($chars);

        for ($i = 0, $result = ''; $i < $length; $i++) {
            $index = mt_rand(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }

        return $result;
    }

}