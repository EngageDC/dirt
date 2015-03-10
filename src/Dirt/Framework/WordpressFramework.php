<?php
namespace Dirt\Framework;

use Dirt\Configuration;

class WordpressFramework extends Framework
{
    /**
     * Full name of the Framework
     * @return string
     */
    public function getName()
    {
        return 'WordPress';
    }

    /**
     * One or more command line shortcuts for the framework
     * must be lowercase.
     * @return array
     */
    public function getShortcuts()
    {
        return array('wordpress', 'wp');
    }

    /**
     * Start the framework installation process
     * @param type $project 
     * @param type $progressCallback 
     */
    public function install($project, $progressCallback = null)
    {
        // Download latest version
        $filename = $this->downloadFile('http://wordpress.org/latest.zip', $project->getDirectory(), $progressCallback);

        // Extract to location
        $this->extractArchive($filename, $project->getDirectory(), $progressCallback);

        // Add gitignore file
        $gitignoreContents = array(
            'wp-config.php',
            'wp-content/*',
            '!wp-content/plugins/',
            '!wp-content/themes/',
            '*.log',
            'sitemap.xml',
            'sitemap.xml.gz'
        );
        file_put_contents($project->getDirectory() . '/public/.gitignore', implode(PHP_EOL, $gitignoreContents));
    }

    public function configureEnvironment($environment, $project, $ssh = NULL)
    {
        // Define source config filenames
        $configFilenames = array(
            'dev' => 'wp-config-sample.php',
            'staging' => 'wp-config.php',
            'production' => 'wp-config.php'
        );

        // Determine base directory
        $configDirectory = '/';
        if (!file_exists($project->getDirectory() . $configDirectory . $configFilenames[$environment])) {
            $configDirectory = '/public/';

            if (!file_exists($project->getDirectory() . $configDirectory . $configFilenames[$environment])) {
                throw new \RuntimeException('Could not find ' . $configFilenames[$environment]);
            }
        }

        // Load database credentials
        $databaseCredentials = $project->getDatabaseCredentials($environment);

        // Load dev config
        $sourceConfig = file_get_contents($project->getDirectory() . $configDirectory . $configFilenames[$environment]);
        $configLines = explode("\n", $sourceConfig);

        // Inject extra configuration lines after the WP_DEBUG line
        $url = '';
        switch ($environment) {
            case 'dev':
                $url = $project->getDevUrl();
                break;

            case 'staging':
                $url = $project->getStagingUrl();
                break;

            case 'production':
                $url = $project->getProductionUrl();
                break;
            
            default:
                break;
        }

        $simpleProjectName = preg_replace("/[^a-zA-Z]/", '', $project->getName());
        $prefix = 'eng'. substr($simpleProjectName, 0, 3) . '_';

        $lineNo = 1;
        $home_found = false;
        $siteurl_found = false;
        foreach ($configLines as &$line) {
            if (strpos($line, "define('DB_NAME'") !== FALSE) {
                $line = "define('DB_NAME', '". $databaseCredentials['database'] ."');";
            } elseif (strpos($line, "define('DB_USER'") !== FALSE) {
                $line = "define('DB_USER', '". $databaseCredentials['username'] ."');";
            } elseif (strpos($line, "define('DB_HOST'") !== FALSE) {
                $line = "define('DB_HOST', '". $databaseCredentials['hostname'] ."');";
            } elseif (strpos($line, "define('DB_PASSWORD'") !== FALSE) {
                $line = "define('DB_PASSWORD', '". $databaseCredentials['password'] ."');";
            } elseif (strpos($line, "\$table_prefix  =") !== FALSE) {
                $line = "\$table_prefix  = '". $prefix ."';";
            } elseif (strpos($line, "define('WP_DEBUG'") !== FALSE) {
                $enableDebugging = (($environment == 'dev') ? 'true' : 'false');
                $line = "define('WP_DEBUG', ". $enableDebugging .");";
                $debugLineNo = $lineNo;
            } elseif (strpos($line, "define('WP_HOME'") !== FALSE) {
                $line = "define('WP_HOME', '". $url ."');";
                $home_found = true;
            }  elseif (strpos($line, "define('WP_SITEURL'") !== FALSE) {
                $path = ($configDirectory == '/') ? '/core' : '';
                $line = "define('WP_SITEURL', '" . $url . $path . "');";
                $siteurl_found = true;     
            }

            $lineNo++;
        }
        


        $extraLines = array(
            '',
            '/**',
            ' * WordPress Site URL.',
            ' *',
            ' * This is automatically configured by dirt depending on the environment.',
            ' * Note that this overrides the URL configured in the database.',
            ' */',
            "define('WP_HOME', '". $url ."');",
            "define('WP_SITEURL', '". $url . (($configDirectory == '/') ? '/core' : '') . "');",
            '',
            '/**',
            ' * Enable/disable caching',
            ' */',
            "define('WP_CACHE', ". (($environment == 'dev') ? 'false' : 'true') .");"
        );

        if (!$home_found || !$siteurl_found) {
            array_splice($configLines, $debugLineNo, 0, $extraLines);
        }

        // Inject warning lines to the top of the configuration file
        $warningLines = array(
            '/**********************************************************************',
            ' * WARNING',
            ' * This file is automatically generated by dirt!',
            ' * You should never edit this file manually unless you understand the',
            ' * implications.',
            ' *********************************************************************/',
            ''
        );
        array_splice($configLines, 1, 0, $warningLines);

        if ($environment == 'dev')
        {
            $devConfig = implode("\n", $configLines);

            // Generate key tokens
            for ($i = 0; $i < 8; $i++) {
                $devConfig = preg_replace('/put your unique phrase here/', $this->generateWordPressToken(), $devConfig, 1);
            }
            file_put_contents($project->getDirectory() . $configDirectory . 'wp-config.php', $devConfig);
        }
        else
        {
            $dir = ($environment == 'staging') ? ('/var/www/sites/' . $project->getStagingUrl(false)) : $project->getProductionDirectory();

            $configContents = implode("\\n", $configLines);
            $configContents = str_replace('"', '\"', $configContents); // Escape quotes
            $configContents = str_replace('`', '\\`', $configContents); // Escape backticks
            $configContents = str_replace('$', '\\$', $configContents); // Escape $
            $response = $ssh->exec('echo -e "'. $configContents .'" > ' . $dir . $configDirectory . 'wp-config.php');

            if (strlen($response) != 0) {
                throw new \RuntimeException('Unexpected response: '. $response);
            }
        }

        if ($environment == 'production')
        {
            // Create symlink
            $ssh->exec('cd ' . $project->getProductionDirectory() . ' && ln -sf public html');

            // Update permissions
            $ssh->exec('chmod -R 777 ' . $project->getProductionDirectory() . '/wp-content');
        }
    }

    private function generateWordPressToken()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?| ';

        $token = '';
        for ($i = 0; $i < 64; $i++ ) {
            $token .= substr($chars, rand(0, strlen($chars) - 1), 1);
        }

        return $token;
    }
}