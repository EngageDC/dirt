<?php
namespace Dirt\Repositories;

use Dirt\Configuration;

class VersionControlRepositoryGitLab implements VersionControlRepository
{
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function create($project) {
        if (!is_object($project) || !($project instanceof \Dirt\Project)) {
            throw new \RuntimeException('Invalid project specified');
        }

        // Authenticate to the GitLab API
        $gitlabClient = new \Gitlab\Client($this->config->scm->domain . '/api/v3/');
        $gitlabClient->authenticate($this->config->scm->private_token, \Gitlab\Client::AUTH_URL_TOKEN);

        // Create repository
        $gitlabProject = NULL;
        try {
            $gitlabProject = $gitlabClient->api('projects')->create(
                $project->getName(),
                array(
                    'description' => $project->getDescription(),
                    'namespace_id' => $this->config->scm->group_id
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

        // Save the SSH repository URL
        $project->setRepositoryUrl($gitlabProject['ssh_url_to_repo']);
    }

    public function delete($project) {
        if (!is_object($project) || !($project instanceof \Dirt\Project)) {
            throw new \RuntimeException('Invalid project specified');
        }

        // Authenticate to the GitLab API
        $gitlabClient = new \Gitlab\Client($this->config->scm->domain . '/api/v3/');
        $gitlabClient->authenticate($this->config->scm->private_token, \Gitlab\Client::AUTH_URL_TOKEN);

        // Search for project by name
        $results = $gitlabClient->api('projects')
            ->search($project->getName());

        // Go through search results
        foreach ($results as $result) {
            // Does the repository URL match?
            if ($result['ssh_url_to_repo'] == $project->getRepositoryUrl()) {
                $gitlabClient->api('projects')->remove($result['id']);
                return; // We're done
            }
        }

        // If we get here, the project couldn't be found
        throw new \RuntimeException('Project couldn\'t be found in GitLab');
    }

    public function exists($project) {
        if (!is_object($project) || !($project instanceof \Dirt\Project)) {
            throw new \RuntimeException('Invalid project specified');
        }

        // Authenticate to the GitLab API
        $gitlabClient = new \Gitlab\Client($this->config->scm->domain . '/api/v3/');
        $gitlabClient->authenticate($this->config->scm->private_token, \Gitlab\Client::AUTH_URL_TOKEN);

        // Search for project by name
        $results = $gitlabClient->api('projects')
            ->search($project->getName());

        // Go through search results
        foreach ($results as $result) {
            // Does the repository URL match?
            if ($result['ssh_url_to_repo'] == $project->getRepositoryUrl()) {
               return true;
            }
        }

        return false;
    }

}