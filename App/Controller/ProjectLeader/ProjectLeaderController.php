<?php

namespace App\Controller\ProjectLeader;

use App\Controller\ProjectMember\ProjectMemberController;
use App\Controller\Message\MessageController;
use App\Model\ProjectLeader;
use App\Model\Project;
use App\Model\Notification;
use App\Model\User;
use App\Model\Task;
use App\Model\Group;
use App\Model\Message;

require __DIR__ . '/../../../vendor/autoload.php';

class ProjectLeaderController extends ProjectMemberController
{
    private ProjectLeader $projectLeader;
    private Project $project;

    public function __construct()
    {
        try {
            parent::__construct();
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function defaultAction(Object|array|string|int $data = null)
    {
    }

    // in here check the user role whether it is project leader regarding the project
    public function auth(): bool
    {
        return parent::auth();
    }
    public function getProjectInfo()
    {
        // print_r($_SESSION);
        $payload = $this->userAuth->getCredentials();
        $project_id = $_SESSION["project_id"];
        $user_id = $payload->id;
        $project = new Project($payload->id);

        // get all data related to the project

        $group = new Group();
        $groups = $group->getAllGroups(array("project_id" => $project_id), array("project_id"));

        $user = new User();
        $data = array("groups" => $groups, "projectData" => $project->getProject(array("id" => $project_id)));

        // get project members' details
        $projectLeaderCondition = "WHERE id IN (SELECT member_id FROM project_join WHERE project_id = :project_id AND role = :role)";
        $projectMemberCondition = "WHERE id IN (SELECT member_id FROM project_join WHERE project_id = :project_id AND ( role = :role OR role = :role2))";
        $groupLeaderCondition = "WHERE id IN (SELECT DISTINCT leader_id FROM groups WHERE project_id = :project_id)";

        $data['projectLeader'] = $user->getAllUsers(array("project_id" => $project_id, "role" => "LEADER"), $projectLeaderCondition);
        $data['projectMembers'] = $user->getAllUsers(array("project_id" => $project_id, "role" => "MEMBER", "role2" => "LEADER"), $projectMemberCondition);
        $data['groupLeaders'] = $user->getAllUsers(array("project_id" => $project_id), $groupLeaderCondition);

        $data += parent::getTaskDeadlines();
        // get user profile
        $user = new User();

        if($user->readUser("id", $payload->id)){
            $data += array("profile" => $user->getUserData()->profile_picture);
        }
        
        $this->sendResponse(
            view: "/project_leader/getProjectInfo.html",
            status: "success",
            content: $project->readProjectsOfUser($user_id, $project_id) ? $data : array()
        );
    }

    public function sendProjectInvitation()
    {
        try {
            // get receiver user name
            $data = file_get_contents('php://input');

            // first check receiver is valid user or not
            // get received user id
            $user = new User();
            $user->readUser("username", $data);
            $receivedUser = $user->getUserData();

            if ($receivedUser) {
                $payload = $this->userAuth->getCredentials();
                $project_id = $_SESSION["project_id"];
                $user_id = $payload->id;

                $date = date("Y-m-d H:i:s");

                $args = array(
                    "projectId" => $project_id,
                    "message" => "Invite to this peoject",
                    "type" => "request",
                    "senderId" => $user_id,
                    "sendTime" => $date,
                    "url" => "http://localhost/public/user/project?id=" . $project_id
                );
            }
            // set notified members
            // get notification id
            $notification = new Notification();
            $notification->createNotification($args, array("projectId", "message", "type", "senderId", "sendTime", "url"));

            $conditions = array(
                "projectId" => $project_id,
                "senderId" => $user_id,
                "sendTime" => $date
            );

            $newNotification = $notification->getNotification($conditions, array("projectId", "senderId", "sendTime"));

            $arguments = array(
                "notificationId" => $newNotification->id,
                "memberId" => $receivedUser->id
            );
            $notification->setNotifiers($arguments, array("notificationId", "memberId"));

            echo (json_encode(array("message" => "Success")));
        } catch (\Throwable $th) {
            echo (json_encode(array("message" => $th)));
        }
    }

    public function createTask($args)
    {

        if ($args['taskname'] && $args['taskdescription']) {
            $status = "TO-DO";
            if ($args['assignedMember']) {
                $user = new User();
                $user->readUser("username", $args['assignedMember']);
                $receivedUser = $user->getUserData();

                if ($receivedUser) {
                    $conditions = array(
                        "project_id" => $_SESSION['project_id'],
                        "member_id" => $receivedUser->id
                    );

                    if ($user->isUserJoinedToProject($conditions)) {
                        $status = "ONGOING";

                        // get leader id
                        $payload = $this->userAuth->getCredentials();
                        $user_id = $payload->id;
                        $date = date("Y-m-d H:i:s");

                        // now we have to send a notification as well 
                        $notificationArgs = array(
                            "projectId" => $_SESSION['project_id'],
                            "message" => "You are assigned to " . $args['taskname'] . " by project leader",
                            "type" => "notification",
                            "senderId" => $user_id,
                            "sendTime" => $date
                        );
                        $notification = new Notification();
                        $notification->createNotification($notificationArgs);

                        $notifyConditions = array(
                            "projectId" => $_SESSION['project_id'],
                            "senderId" => $user_id,
                            "sendTime" => $date
                        );
                        $newNotification = $notification->getNotificationData($notifyConditions);
                        $newNotificationId = $newNotification[0]->id;

                        $notifyMemberArgs = array(
                            "notificationId" => $newNotificationId,
                            "memberId" => $receivedUser->id
                        );
                        $notification->setNotifiedMembers($notifyMemberArgs);
                    }
                }
            }

            $data = array(
                "project_id" => $_SESSION['project_id'],
                "description" => $args['taskdescription'],
                "deadline" => $args['taskdeadline'],
                "task_name" => $args['taskname'],
                "priority" => $args['priority'],
                "status" => $status
            );

            $task = new Task();
            if ($task->createTask($data, array("project_id", "description", "deadline", "task_name", "priority", "status"))) {
                header("Location: http://localhost/public/user/project?id=" . $_SESSION['project_id']);
            } else {
                echo "Fail";
            }
        } else {
            header("Location: http://localhost/public/user/project?id=" . $_SESSION['project_id']);
        }
    }

    public function rearangeTask()
    {
        $data = json_decode(file_get_contents('php://input'));
        $project_id = $_SESSION["project_id"];

        $args = array(
            "status" => $data->new_board,
            "project_id" => $project_id,
            "task_name" => $data->task_name
        );
        $conditions = array("project_id", "task_name");
        $updates = array("status");

        $task = new Task();
        $message = new Message();
        $notification = new Notification();

        $successMessage = "";

        if ($data->new_board === "TO-DO") {
            $args['memberId'] = NULL;
            $updates[] = "memberId";

            // we have to delete all messages as well related to that task
            $rearrangedTask = $task->getTask(array("project_id" => $project_id, "task_name" => $data->task_name), array("project_id","task_name"));
            $messageCondition = "WHERE id IN (SELECT message_id FROM project_task_feedback_message WHERE task_id = " . $rearrangedTask->task_id . " AND project_id = " . $project_id . ")";
        
            $message->deleteMessage($messageCondition, "message");
        
            // we have to delete notifications related to that task messages
            $notificationCondition = "WHERE id IN (SELECT notification_id FROM task_notification WHERE task_id = " . $rearrangedTask->task_id . ")";
            $notification->deleteNotification($notificationCondition, "notifications");
        }
        try {
            $task->updateTask($args, $updates, $conditions);
            $successMessage = "Reported successfully";
        } catch (\Throwable $th) {
            $successMessage = "Failed to rearange the task";
        }
        $this->sendJsonResponse(
            status: "success",
            content: [
                "message" => $successMessage
            ]
        );
    }

    public function assignTask()
    {
        $data = json_decode(file_get_contents('php://input'));

        $user = new User();
        $user->readUser("username", $data->member_username);
        $receivedUser = $user->getUserData();

        $payload = $this->userAuth->getCredentials();
        $project_id = $_SESSION["project_id"];
        $user_id = $payload->id;

        if ($receivedUser) {
            $args = array(
                "status" => "ONGOING",
                "memberId" => $receivedUser->id,
                "project_id" => $project_id,
                "task_name" => $data->task_name
            );

            $task = new Task();
            $message = "";

            try {
                // $task->pickupTask($args);
                $message = "Successfully handovered the task.";

                // send notification to leader
                $date = date("Y-m-d H:i:s");

                // now we have to send a notification as well 
                $notificationArgs = array(
                    "projectId" => $project_id,
                    "message" => "Leader assigned you to " . $data->task_name . '.',
                    "type" => "notification",
                    "senderId" => $user_id,
                    "sendTime" => $date,
                    "url" => `http://localhost/public/user/project?id=${project_id}`
                );
                $notification = new Notification();
                $notification->createNotification($notificationArgs, array("projectId", "message", "type", "senderId", "sendTime", "url"));

                $notifyConditions = array(
                    "projectId" => $project_id,
                    "senderId" => $user_id,
                    "sendTime" => $date
                );
                $newNotification = $notification->getNotification($notifyConditions);

                $notifyMemberArgs = array(
                    "notificationId" => $newNotification->id,
                    "memberId" => $receivedUser->id
                );
                $notification->setNotifiers($notifyMemberArgs, array("notificationId", "memberId"));
            } catch (\Throwable $th) {
                $message = "Failed to handover the task: " . $th->getMessage();
            }

            $this->sendJsonResponse(
                status: "success",
                content: [
                    "message" => $message
                ]
            );
        }
    }
    public function createGroup()
    {
        $data = $_POST;
        // var_dump($data);
        $project_id = $_SESSION['project_id'];

        $payload = $this->userAuth->getCredentials();
        $user_id = $payload->id;

        $leaderId = $user_id;
        if($data['assignMember']){
            $leaderId = $data['assignMember'];
        }

        $args = array(
            "group_name" => $data['group_name'],
            "task_name" => $data['task_name'],
            "description" => $data['group_description'],
            "project_id" => $project_id,
            "leader_id" => $leaderId
        );
        $keys = array("group_name", "task_name", "description", "project_id", "leader_id");

        // set the task as well
        $taskArgs = array(
            "project_id" => $project_id,
            "description" => $data['group_description'],
            "task_name" => $data['task_name'],
            "priority" => "high",
            "status" => "ONGOING",
            "assign_type" => "group",
            "memberId" => $user_id,
        );

        $group = new Group();
        $task = new Task();

        $message = "";
        try {
            $group->createGroup($args, $keys);
            $task->createTask($taskArgs, array("project_id", "description", "task_name", "priority", "status", "assign_type", "memberId"));

            // until project leader add a new group leader, he or she will be the group leader
            $newGroup = $group->getGroup(array("group_name" => $data['group_name'], "project_id" => $project_id,), array("group_name", "project_id"));
            $addMemberArgs = array(
                "group_id" => $newGroup->id,
                "member_id" => $user_id,
                "role" => "LEADER",
                "joined" => date("Y-m-d H:i:s")
            );

            $group->addGroupMember($addMemberArgs, array("group_id", "member_id", "role", "joined"));
            $message = "Successfully created";
        } catch (\Throwable $th) {
            $message = $th->getMessage();
        }

        $this->sendJsonResponse(
            status: "success",
            content: [
                "message" => $message
            ]
        );
    }

    public function addAnnouncement(){
        $data = json_decode(file_get_contents('php://input'));

        $successMessage = "";
        $payload = $this->userAuth->getCredentials();
        $messageController = new MessageController();
        $message = new Message();
        $notification =  new Notification();
        $project = new Project($payload->id);

        $date = date('Y-m-d H:i:s');
        $args = array(
            "sender_id" => $payload->id,
            "stamp" => $date,
            "message_type" => "PROJECT_ANNOUNCEMENT",
            "msg" => $data->announcementMessage
        );
        try {
            $messageController->send($args, array("sender_id", "stamp", "message_type", "msg"));

            $newMessage = $message->getMessage(array("sender_id" => $payload->id, "stamp" => $date, "message_type" => "PROJECT_ANNOUNCEMENT"), array("sender_id", "stamp", "message_type"));
            
            $messageTypeArgs = array(
                "message_id" => $newMessage->id,
                "project_id" => $_SESSION['project_id'],
                "heading" => $data->announcementHeading
            );

            $message->setMessageType($messageTypeArgs, array("message_id", "project_id", "heading"), "project_announcement");
            $successMessage = "Message sent successfully";
        } catch (\Throwable $th) {
            // $successMessage = "Message sent failed";
            throw $th;
        }

        // set notifications
        // try {
        //     $date = date("Y-m-d H:i:s");

        //     $notificationArgs = array(
        //         "projectId" => $_SESSION['project_id'],
        //         "message" => $data->announcementHeading,
        //         "type" => "notification",
        //         "senderId" => $payload->id,
        //         "sendTime" => $date,
        //         "url" => "http://localhost/public/projectmember/getinfo"
        //     );

        //     $notification->createNotification($notificationArgs, array("projectId", "message", "type", "senderId", "sendTime", "url"));
        //     $notifyConditions = array(
        //         "projectId" => $_SESSION['project_id'],
        //         "senderId" => $payload->id,
        //         "sendTime" => $date
        //     );
        //     $newNotification = $notification->getNotification($notifyConditions, array("projectId", "senderId", "sendTime"));

        //     $members = $project->getProjectUsers("WHERE project_id = " . $_SESSION['project_id'] . " AND `role` = 'MEMBER'");

        //     foreach($members as $member){
        //         $notification->setNotifiers(array("notificationId" => $newNotification->id, "memberId" => $member->member_id), array("notificationId", "memberId"));
        //     }

        // } catch (\Throwable $th) {
        //     throw $th;
        // }
        $this->sendJsonResponse(
            status: "success",
            content: [
                "message" => $successMessage
            ]
        );
    }

}
