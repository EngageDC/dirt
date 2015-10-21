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

    private $databaseCredentials = [];

    private $config = null;

    private $wpengine;

    public function generateProperties() {
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

        $project = new Project;

        $project->setName($projectData->name, $projectData->name_full);
        $project->setDatabaseCredentials((array)$projectData->database);

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

        if (!empty($projectData->wpengine)) {
            $project->wpengine = $projectData->wpengine;
        }
        
        $project->parseGitConfig(); 

        return $project;
    }

    /**
     * Returns the Dirt configuration instance for the project
     * @return Dirt\Configuration Dirt configuration instance
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Sets the Dirt configuration instance
     * @param Dirt\Configuration $config Dirt configuration instance
     */
    public function setConfig($config) {
        $this->config = $config;
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
     * Set's the project name and generates paths among
     * other things based on the name
     * @param type $description 
     */
    public function setName($projectName, $projectNameFull = null)
    {
        // If both the regular and simple project name has already been specified
        // then just use these.
        if ($projectNameFull != null) {
            $this->projectNameSimple = $projectName;
            $this->projectNameFull = $projectNameFull;
        } else {
            $this->projectNameFull = $projectName;

            // Generate simple project name from the full project name
            $simpleName = str_replace(' ', '-', $this->projectNameFull);
            $this->projectNameSimple = trim(preg_replace('/[^a-zA-Z0-9-]+/', '', $simpleName), '-');

            // Set urls and directory based off of the given project name
            foreach (['dev', 'staging', 'production'] as $environment) {
                if ($this->config && isset($this->config->environments->$environment->domain_suffix)) {
                    $this->{$environment . 'Url'} = strtolower($this->projectNameSimple) . $this->config->environments->$environment->domain_suffix;
                } else {
                    $this->{$environment . 'Url'} = strtolower($this->projectNameSimple);
                }
            }
        }
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
            $this->setRepositoryUrl(null);
        } else {
            $this->setRepositoryUrl($config['remote origin']['url']);
        }
    }

    public function setWpConfig($wp_config) {
        $this->wpengine = $wp_config;
    }

    public function getWpConfig() {
       return $this->wpengine;
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
     * Returns the correct HTTP url for the given environment
     * @param  string $environment dev|staging|production
     * @param  string $includeProtocol Whether the protocol (i.e. http://) should be included
     * @return string url
     */
    public function urlForEnvironment($environment, $includeProtocol = true) {
        switch ($environment[0]) {
            case 'd':
                return $this->getDevUrl($includeProtocol);

            case 's':
                return $this->getStagingUrl($includeProtocol);

            case 'p':
                return $this->getProductionUrl($includeProtocol);
            
            default:
                throw new \RuntimeException('Unknown environment ' . $environment);
        }
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
     * Sets the database credentials. This is used if existing
     * database credentials are loaded from a configuration file
     * and needs to be filled into the model
     * @param array $databaseCredentials Associative array with database credentials for each environment
     */
    public function setDatabaseCredentials($databaseCredentials)
    {
        $this->databaseCredentials = $databaseCredentials;
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
     * Executes "vagrant ssh-config" and returns the output
     * @return string "vagrant ssh-config" output for the project
     */
    public function getSSHConfig() {
        $process = new Process('vagrant ssh-config', $this->getDirectory());
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Could not get dev SSH credentials, make sure local virtual machine is running by executing "vagrant up": ' . $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Returns dev SSH credentials from local VM, using Vagrant
     * @return array
     */
    public function getDevelopmentServer() {
        $credentials = array();

        $lines = explode(PHP_EOL, $this->getSSHConfig());

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line))
                continue;

            // The key is the first word
            list($key) = explode(' ', $line);

            // Keep the remaining part of the line
            $value = substr($line, strlen($key) + 1);

            $credentials[$key] = trim($value, '"');
        }

        return (object)array(
            'hostname' => $credentials['HostName'],
            'port' => $credentials['Port'],
            'keyfile' => $credentials['IdentityFile'],
            'username' => $credentials['User']
        );
    }

    /**
     * This will return the uploads folder as a relative path from the project root
     * If the project does not have a uploads folder, it will return null
     * @return string|null Path to uploads folder
     */
    public function getUploadsFolder() {
        if ($this->getFramework()->getName() == 'WordPress') {
            return 'public/wp-content/uploads';
        } elseif ($this->getFramework()->getName() == 'Laravel 4' && file_exists($this->getDirectory() . '/app/storage/uploads')) {
            return 'app/storage/uploads';
        } elseif ($this->getFramework()->getName() == 'Laravel 5' && file_exists($this->getDirectory() . '/storage/uploads')) {
            return 'storage/uploads';
        }

        return null;
    }

    /**
     * Get the absolute folder on the server for the site on a given environment
     */
    public function getFolderForEnvironment($environment) {
        switch ($environment) {
            case 'staging':
                return '/var/www/sites/' . $this->getStagingUrl(false);

            case 'production':
                return $this->getProductionDirectory();
            
            default:
                throw new \RuntimeException('Unknown environment ' . $environment);
        }
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

        if (!empty($this->wpengine)) {
            $projectData['wpengine'] = array();
            foreach ($this->wpengine as $key=>$value) {
                $projectData['wpengine'][$key] = $value;
            }
        }
        return json_encode($projectData, JSON_PRETTY_PRINT);    
    }

    /**
     * Save Dirtfile.json for the project
     */
    public function save()
    {   
        var_dump($this);
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
