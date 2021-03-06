<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Exceptions\HTTPException;
use App\Factory\ResponseFactory;
use App\Service\ProjectService;
use App\Service\TaskService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends BaseController
{

    public function getTasks() : Response
    {
        return ResponseFactory::createSuccessResponse(
            '',
            $this->getDoctrine()
                ->getRepository(Task::class)
                ->findAll()
        );
    }
    
    public function getTaskById(int $id, TaskService $service) : Response
    {
        try {
            return ResponseFactory::createJsonResponse(200, '', $service->getTaskById($id));
        } catch(HTTPException $exception) {
            return $exception->getJsonResponse();
        }
    }
    
    public function createTask(int $id, Request $request, TaskService $service, ProjectService $projectService) : Response
    {
        try {
            $task = $service->createTaskFromRequest($id, $projectService, $request);
            $task->getUsers()->add($this->getAuthenticatedUser($request));
        } catch(HTTPException $exception) {
            return $exception->getJsonResponse();
        }
        $manager = $this->getDoctrine()->getManager();
        $manager->persist($task);
        $manager->flush();
        return ResponseFactory::createSuccessResponse(
            sprintf('Das Arbeitspaket %s wurde erstellt.', $task->getTitle()),
            $task
        );
    }
    
    public function addUserToTask(int $task_id, int $user_id) : Response
    {
        $task = $this->getDoctrine()->getRepository(Task::class)->find($task_id);
        if(!$task) {
            return ResponseFactory::createJsonResponse(404, sprintf(
                'Es existiert kein Arbeitspaket mit der ID %s',
                $task_id
            ));
        }
        $user = $this->getDoctrine()->getRepository(User::class)->find($user_id);
        if(!$user) {
            return ResponseFactory::createJsonResponse(404, sprintf(
                'Es existiert kein Benutzer mit der ID %s',
                $user_id
            ));
        }
        $user->getTasks()->add($task);
        $this->getDoctrine()->getManager()->flush();
        return ResponseFactory::createSuccessResponse(
            sprintf(
                'Der Benutzer `%s` wurde zum Arbeitspaket `%s` hinzugefügt.',
                $user->getName(),
                $task->getName()
            )
        );
    }
    
    public function updateTask(int $id, Request $request, TaskService $service) : Response
    {
        try {
            $task = $service->updateTaskFromRequest($id, $request);
        } catch(HTTPException $exception) {
            return $exception->getJsonResponse();
        }
        $this->getDoctrine()->getManager()->flush();
        return ResponseFactory::createSuccessResponse(
            'Das Arbeitspaket wurde erfolgreich aktualisiert.',
            $task
        );
    }
    
    public function deleteTask(int $id, TaskService $service) : Response
    {
        try {
            $task = $service->getTaskById($id);
            $manager = $this->getDoctrine()->getManager();
            $manager->remove($task);
            $manager->flush();
            return ResponseFactory::createSuccessResponse(sprintf('Das Arbeitspaket %s wurde gelöscht.', $task->getTitle()));
        } catch(HTTPException $exception) {
            return $exception->getJsonResponse();
        }
    }
    
    public function changeTaskStatus(Request $request, int $id, TaskService $service) : Response
    {
        try {
            $task = $service->getTaskById($id);
            $task = $service->patchTaskStatusFromRequest($task, $request);
            $this->getDoctrine()->getManager()->flush();
            switch($task->getStatus()) {
                case Task::STATUS_OPEN:
                    return ResponseFactory::createSuccessResponse(
                        sprintf('Das Arbeitspaket %s wurde neu eröffnet.', $task->getTitle()),
                        $task
                    );
                case Task::STATUS_FINISHED:
                    return ResponseFactory::createSuccessResponse(
                        sprintf('Das Arbeitspaket %s wurde beendet.', $task->getTitle()),
                        $task
                    );
                default:
                    return ResponseFactory::createServerErrorResponse('Es ist ein Fehler beim ändern des Status aufgetreten.');
            }
        } catch(HTTPException $exception) {
            return $exception->getJsonResponse();
        }
    }
    
}
