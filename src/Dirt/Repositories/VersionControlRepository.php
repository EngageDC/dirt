<?php
namespace Dirt\Repositories;

interface VersionControlRepository
{

    /**
     * Create a new repository for the specified project
     * @param  Project $project Project object
     * @throws \Exception
     */
    public function createRepository($project);


    /**
     * Deletes the repository for the specified project
     * @param  Project $project Project object
     * @throws \Exception
     */
    public function deleteRepository($project);

}