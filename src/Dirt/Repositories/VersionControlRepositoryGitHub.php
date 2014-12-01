<?php
namespace Dirt\Repositories;

use Dirt\Configuration;

class VersionControlRepositoryGitHub implements VersionControlRepository
{
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function create($project) {
        if (!is_object($project) || !($project instanceof \Dirt\Project)) {
            throw new \RuntimeException('Invalid project specified');
        }

        $githubClient = new \Github\Client();
        // Set authentication info
        $githubClient->authenticate(
                $this->config->scm->username,
                $this->config->scm->password,
                \Github\Client::AUTH_HTTP_PASSWORD
        );

        // Create repository
        $repo = $githubClient->api('repo')->create(
            $project->getName(),
            $project->getDescription(),
            $project->getStagingUrl(),
            false,
            $this->config->scm->organization
        );

        // Save the SSH repository URL
        $project->setRepositoryUrl($repo['ssh_url']);                
    }

    public function delete($project) {
        throw new \BadFunctionCallException('Function not implemented yet'); // TODO
    }

    public function exists($project) {
        throw new \BadFunctionCallException('Function not implemented yet'); // TODO
    }

}