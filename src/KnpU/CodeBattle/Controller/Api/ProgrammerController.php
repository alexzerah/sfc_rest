<?php

namespace KnpU\CodeBattle\Controller\Api;

use KnpU\CodeBattle\Api\ApiProblem;
use KnpU\CodeBattle\Api\ApiProblemException;
use KnpU\CodeBattle\Controller\BaseController;
use KnpU\CodeBattle\Model\Programmer;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProgrammerController extends BaseController
{
    protected function addRoutes(ControllerCollection $controllers)
    {
        $controllers->post('/api/programmers', [$this, 'newAction']);
        $controllers->get('/api/programmers', [$this, 'listAction']);
        $controllers->put('/api/programmers/{nickname}', [$this, 'updateAction']);
        // point PUT and PATCH at the same controller
        $controllers->put('/api/programmers/{nickname}', [$this, 'updateAction']);
        $controllers->delete('/api/programmers/{nickname}', [$this, 'deleteAction']);
        // PATCH isn't natively supported, hence the different syntax
        $controllers->match('/api/programmers/{nickname}', [$this, 'updateAction'])
            ->method('PATCH');
        $controllers->get('/api/programmers/{nickname}', [$this, 'showAction'])
            ->bind('api_programmers_show');
    }

    public function newAction(Request $request)
    {
        $this->enforceUserSecurity();

        $programmer = new Programmer();

        $this->handleRequest($request, $programmer);

        if ($errors = $this->validate($programmer)) {
            $this->throwApiProblemValidationException($errors);
        }

        $this->save($programmer);

        $response = $this->createApiResponse($programmer, 201);

        $programmerUrl = $this->generateUrl(
            'api_programmers_show',
            ['nickname' => $programmer->nickname]
        );
        $response->headers->set('Location', $programmerUrl);

        return $response;
    }

    public function updateAction($nickname, Request $request)
    {
        $programmer = $this->getProgrammerRepository()->findOneByNickname($nickname);

        $this->enforceProgrammerOwnershipSecurity($programmer);

        if (!$programmer) {
            $this->throw404();
        }

        $this->handleRequest($request, $programmer);

        if ($errors = $this->validate($programmer)) {
            $this->throwApiProblemValidationException($errors);
        }

        $this->save($programmer);

        $json = $this->serialize($programmer);
        $response = new Response($json, 200);

        return $response;
    }

    public function showAction($nickname)
    {
        $programmer = $this->getProgrammerRepository()
            ->findOneByNickname($nickname);

        if (!$programmer) {
            $this->throw404("The programmer $nickname does not exist!");
        }

        $json = $this->serialize($programmer);
        $response = new Response($json, 200);

        return $response;
    }

    public function listAction()
    {
        $programmers = $this->getProgrammerRepository()->findAll();
        $data = ['programmers' => $programmers];
        $json = $this->serialize($data);

        $response = new Response($json, 200);

        return $response;
    }

    public function deleteAction($nickname)
    {
        $programmer = $this->getProgrammerRepository()->findOneByNickname($nickname);

        $this->enforceProgrammerOwnershipSecurity($programmer);

        if ($programmer) {
            $this->delete($programmer);
        }

        return new Response(null, 204);
    }

    private function handleRequest(Request $request, Programmer $programmer)
    {
        $data = json_decode($request->getContent(), true);
        $isNew = !$programmer->id;

        if ($data === null) {
            $problem = new ApiProblem(
                400,
                ApiProblem::TYPE_INVALID_REQUEST_BODY_FORMAT
            );

            throw new ApiProblemException($problem);
        }

        // determine which properties should be changeable on this request
        $apiProperties = ['avatarNumber', 'tagLine'];
        if ($isNew) {
            $apiProperties[] = 'nickname';
        }

        // update the properties
        foreach ($apiProperties as $property) {
            // if a property is missing on PATCH, that's ok - just skip it
            if (!isset($data[$property]) && $request->isMethod('PATCH')) {
                continue;
            }

            $val = isset($data[$property]) ? $data[$property] : null;
            $programmer->$property = $val;
        }

        $programmer->userId = $this->getLoggedInUser()->id;
    }

    private function throwApiProblemValidationException(array $errors)
    {
        $apiProblem = new ApiProblem(
            400,
            ApiProblem::TYPE_VALIDATION_ERROR
        );

        $apiProblem->set('errors', $errors);

        throw new ApiProblemException($apiProblem);
    }
}
