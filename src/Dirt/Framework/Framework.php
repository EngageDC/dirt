<?php
namespace Dirt\Framework;

abstract class Framework
{
	/**
	 * Returns a list with instances of available frameworks
	 * @return array
	 */
	public static function availableFrameworks()
	{
		// Define frameworks in reusable static variable
		static $frameworks = array();

		// Find available frameworks from filesystem
		if (count($frameworks) <= 0) { // Only run this operation once
			$currentFilename = basename(__FILE__);
			if ($handle = opendir(dirname(__FILE__))) {
			    while (false !== ($entry = readdir($handle))) {
			        if ($entry != $currentFilename && substr($entry, -4) == '.php') {
			        	// Normalize class name and add a new instance to the array
			        	$className = '\\Dirt\\Framework\\' . substr($entry, 0, -4);
			        	$frameworks[] = new $className;
			        }
			    }
			    closedir($handle);
			}
		}

		// Instances of available frameworks
		return $frameworks;
	}

    /**
     * Returns a list of shortcuts for available frameworks
     * @param bool $onePerFramework Return only the first shortcut for each framework?
     * @return array
     */
    public static function validFrameworkShortcuts($onePerFramework = true) {
        $names = array();

        foreach (self::availableFrameworks() as $framework) {
            $frameworkShortcuts = $framework->getShortcuts();

            if ($onePerFramework) {
                $names[] = $frameworkShortcuts[0];
            } else {
                $names = $names + $frameworkShortcuts;
            }
        }

        return $names;
    }

    /**
     * Returns a new Framework instance from a given name
     * @param type $name 
     */
    public static function fromName($name) {
        foreach (self::availableFrameworks() as $framework) {
            if ($framework->getName() == $name) {
                return $framework;
            }
        }

        return FALSE;
    }

	/**
     * Downloads a file from the given URL in the current project's working directory
     * Does a simple guess of filename from the URL, if it has not been specified
     * @param type $url 
     * @param type $directory 
     * @param type $progressCallback 
     * @param type $filename 
     */
    protected function downloadFile($url, $directory, $progressCallback = null, $filename = null)
    {
        // Show initial info
        if (!is_null($progressCallback)) {
            $progressCallback("\t" . 'Downloading ' . $url);
        }

        // Set up file handles
        $remoteFile = fopen($url, 'rb');
        $localFilename = $directory . '/' . (is_null($filename) ? basename($url) : $filename);
        
        if ($remoteFile) {
            $localFile = fopen($localFilename, 'wb');

            if ($localFile) {
                $i = 0;
                while (!feof($remoteFile)) {
                    fwrite($localFile, fread($remoteFile, 1024 * 8), 1024 * 8);

                    if (!is_null($progressCallback) && $i % 100 == 0) {
                        $progressCallback('.');
                    }
                    $i++;
                }
            } else {
                if (!is_null($progressCallback)) {
                    $progressCallback('<error>Error: Could not write to local file</error>' . PHP_EOL);
                    exit(1);
                } else {
                    throw new \RuntimeException('Could not write to local file');
                }
            }

            if ($remoteFile) {
                fclose($remoteFile);
            }

            if ($localFile) {
                fclose($localFile);
            }

            if (!is_null($progressCallback)) {
                $progressCallback('<info>OK</info>' . PHP_EOL);
            }
        } else {
            if (!is_null($progressCallback)) {
                $progressCallback('<error>Error: Could not download ' . $url .'</error>' . PHP_EOL);
                exit(1);
            } else {
                throw new \RuntimeException('Could not download ' . $url);
            }
        }

        return $localFilename;
    }

    /**
     * Extracts a zip file from the given filename to the given directory
     * with an optional progress callback closure
     * @param type $filename 
     * @param type $directory 
     * @param type $progressCallback 
     */
    protected function extractArchive($filename, $directory, $progressCallback = null)
    {
        // Extract archive
        if (!is_null($progressCallback))
            $progressCallback("\t" . 'Extracting ' . $filename . ' ');

        $zip = new \ZipArchive;
        $res = $zip->open($filename);
        if ($res === TRUE) {
            $firstFile = $zip->statIndex(0);
            $zip->extractTo($directory);
            $zip->close();

            // Rename directory to public to preserve a standardized directory naming scheme
            rename(
                $directory . '/' . $firstFile['name'],
                $directory . '/public'
            );

            // Remove original zip file
            unlink($filename);

            if (!is_null($progressCallback)) {
                $progressCallback('<info>OK</info>' . PHP_EOL);
            }
        } else {
            if (!is_null($progressCallback)) {
                $progressCallback('<error>Error: '. $res .'</error>' . PHP_EOL);
                exit(1);
            } else {
                throw new \RuntimeException('Could not extract archive: ' . $res);
            }
        }
    }

    public abstract function getName();
    public abstract function getShortcuts();
    public abstract function install($project, $progressCallback = null);
    public function configureEnvironment($environment, $project, $ssh = NULL) {} // Default implementation
}