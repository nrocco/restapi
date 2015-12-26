<?php

namespace SilexRestApi\Controllers;

use SilexRestApi\Services\AuthService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RestApiAuthController
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        $username = $request->request->get('username');
        $password = $request->request->get('password');

        if (!$username or !$password) {
            return new JsonResponse(['message' => 'You must supply a username and password'], 400);
        }

        if (true === $this->authService->verifyCredentials($username, $password)) {
            $token = $this->authService->createJwtTokenForUser($username);
            $cookie = $this->authService->createCookieForToken($token);

            $response = new JsonResponse(['token' => $token]);
            $response->headers->setCookie($cookie);

            if (true === $request->request->has('redirect')) {
                $response->headers->set('Location', $request->request->get('redirect'));
                $response->setStatusCode(302);
            }

            return $response;
        }

        return new JsonResponse(['message' => 'Unauthorized'], 401);
    }

    public function logout(Request $request)
    {
        $response = new JsonResponse(['message' => 'Logged out'], 200);

        $cookie = $this->authService->deleteCookie();
        $response->headers->setCookie($cookie);

        return $response;
    }
}
