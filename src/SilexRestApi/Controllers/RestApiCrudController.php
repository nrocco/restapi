<?php

namespace SilexRestApi\Controllers;

use RestApi\RestApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RestApiCrudController
{
    protected $restApi;

    public function __construct(RestApi $restApi)
    {
        $this->restApi = $restApi;
    }

    public function listResources(Request $request)
    {
        return $this->json($this->restApi->listResources());
    }

    public function readCollection(Request $request, $table)
    {
        return $this->json($this->restApi->readCollection($table, $request->query->all()));
    }

    public function createResource(Request $request, $table)
    {
        $this->jsonRequestBodyParser($request);

        $params = array_merge(
            $request->request->all(),
            $request->files->all()
        );

        return $this->json($this->restApi->createResource($table, $params));
    }

    public function readResource(Request $request, $table, $pk)
    {
        return $this->json($this->restApi->readResource($table, $request->query->all()));
    }

    public function updateResource(Request $request, $table, $pk)
    {
        $this->jsonRequestBodyParser($request);

        $params = array_merge(
            $request->request->all(),
            $request->files->all()
        );

        return $this->json($this->restApi->updateResource($table, $pk, $params));
    }

    public function deleteResource(Request $request, $table, $pk)
    {
        return $this->json($this->restApi->deleteResource($table, $pk));
    }

    protected function json($response)
    {
        return new JsonResponse($response['body'], $response['code'], $response['headers']);
    }

    protected function jsonRequestBodyParser(Request &$request)
    {
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data) ? $data : array());
        }
    }
}
