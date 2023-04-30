<?php

namespace App\Model;

require __DIR__ . '/../../vendor/autoload.php';

use App\CrudUtil\CrudUtil;

class ProjectLeader implements Model
{

    private CrudUtil $crud_util;
    private array|object $project_data;
    private array|object $project_member_data;
    private array|object|null $message_data = null;

    public function __construct(string|int $project_id)
    {
        try {
            $this->crud_util = new CrudUtil();
            // CAUTION if the project is not found an exception is thrown
            if (!$this->readProjectData($project_id)) {
                throw new \Exception("Project cannot be found");
            } // otherwise the project details can be viewed using project_data
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function getReportData(string|int $project_id)
    {
        try {
            throw new \Exception("Not implemented yet");
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function readProjectData(string|int $project_id): bool
    {
        try {
            $sql_string = "SELECT * FROM `project` WHERE `id` = :id";
            $args = array("id" => $project_id);
            // execute the query
            $result = $this->crud_util->execute($sql_string, $args);
            if ($result->getCount() > 0) {
                $this->project_data = $result->getFirstResult(); // get all the results or just one result this is an array of objects
                return true;
            } else {
                return false;
            }
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function getProjectData(): object|array|null|bool
    {
        return $this->project_data;
    }

    public function getMessageData(): object|array|null|bool
    {
        return $this->message_data;
    }
}
