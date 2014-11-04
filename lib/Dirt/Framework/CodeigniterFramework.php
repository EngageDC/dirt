<?php
namespace Dirt\Framework;

use Dirt\Configuration;

class CodeigniterFramework extends Framework
{
	/**
	 * Full name of the Framework
	 * @return string
	 */
    public function getName()
    {
    	return 'CodeIgniter';
    }

    /**
     * One or more command line shortcuts for the framework
     * must be lowercase.
     * @return array
     */
    public function getShortcuts()
    {
    	return array('codeigniter', 'ci');
    }

    /**
     * Start the framework installation process
     * @param Project $project 
     * @param function $progressCallback 
     */
    public function install($project, $progressCallback = null)
    {
        // Download latest version
        $filename = $this->downloadFile('http://ellislab.com/codeigniter/download', $project->getDirectory(), $progressCallback, 'codeigniter.zip');

        // Extract to location
        $this->extractArchive($filename, $project->getDirectory(), $progressCallback);
    }
}