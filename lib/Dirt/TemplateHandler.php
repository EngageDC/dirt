<?php
namespace Dirt;

class TemplateHandler {

	private $project;

	public function setProject($project) {
		$this->project = $project;
	}

	private function getTemplatePlaceholders() {
		// Template variables
        $devDatabaseCredentials = $this->project->getDatabaseCredentials('dev');

        return [
            '__PROJECT_NAME__' => $this->project->getName(false),
            '__PROJECT_NAME_SIMPLE__' => $this->project->getName(true),
            '__PROJECT_DESCRIPTION__' => $this->project->getDescription(),
            '__DEV_URL__' => $this->project->getDevUrl(false),
            '__STAGING_URL__' => $this->project->getStagingUrl(false),
            '__DATABASE_USERNAME__' => $devDatabaseCredentials['username'],
            '__DATABASE_PASSWORD__' => $devDatabaseCredentials['password'],
            '__DATABASE_NAME__' => $devDatabaseCredentials['database'],
            '__IPADDRESS__' => $this->project->getIpAddress()
        ];
	}

	private function getTemplateFilePath($filename) {
		// First look in team templates folder
		$teamTemplatesFolder = __DIR__ . '/../../team/templates/';
		if (file_exists($teamTemplatesFolder . '/' . $filename)) {
			return $teamTemplatesFolder . '/' . $filename;
		}

		// No dice? Use the default template then
		$defaultTemplatesFolder = __DIR__ . '/Templates/';
		return $defaultTemplatesFolder . '/' . $filename;
	}

	public function generateTemplate($filename) {
		// Get template
		$templateContents = file_get_contents($this->getTemplateFilePath($filename));

		// Fill in the placeholders
		$variables = $this->getTemplatePlaceholders();

        $templateContents = str_replace(
        	array_keys($variables),
        	array_values($variables),
        	$templateContents
        );

        return $templateContents;
	}

	public function writeTemplate($filename) {
		$contents = $this->generateTemplate($filename);

		// Special case for gitignore file
        if ($filename == 'gitignore') {
        	$filename = '.' . $filename;
        }

        // Write the file to the project directory
        file_put_contents($this->project->getDirectory() . '/' . $filename, $contents);
	}

}