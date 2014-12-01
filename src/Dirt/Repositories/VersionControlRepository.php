<?php
namespace Dirt\Repositories;

interface VersionControlRepository
{

    /**
     * Create a new repository for the specified project
     * @param  Project $project Project object
     * @throws \Exception
     */
    public function create($project);


    /**
     * Deletes the repository for the specified project
     * @param  Project $project Project object
     * @throws \Exception
     */
    public function delete($project);

    /**
     * Checks if the specific project exists
     * @param  Project $project Project object
     */
    public function exists($project);

}