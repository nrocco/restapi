<?php

namespace SilexRestApi\Services;

use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

class AuthService
{
    protected $options;
    protected $users;

    public function __construct($options, $users)
    {
        $this->options = $options;
        $this->users = $users;
    }

    public function verifyCredentials($username, $password)
    {
        return true === password_verify($password, $this->users[$username]);
    }

    public function validateJwtToken($token)
    {
        try {
            $decodedToken = JWT::decode(
                $token,
                $this->options['token_secret_key'],
                $this->options['token_algorithms']
            );
        } catch (\Exception $e) {
            return false;
        }

        if (!isset($decodedToken->iss) or $decodedToken->iss !== $this->options['token_issuer']) {
            return false;
        }

        if (!array_key_exists($decodedToken->user, $this->users)) {
            return false;
        }

        return $decodedToken;
    }

    public function getAuthenticatedUserFromRequest(Request $request)
    {
        // Check cookie first
        if (true === $request->cookies->has('TOKEN')) {
            if ($token = $this->validateJwtToken($request->cookies->get('TOKEN'))) {
                return $token->user;
            }
        }

        // Check basic authentication
        if (true === $request->server->has('PHP_AUTH_USER')) {
            $username = $request->server->get('PHP_AUTH_USER');
            $password = $request->server->get('PHP_AUTH_PW');

            if (true === $this->verifyCredentials($username, $password)) {
                return $username;
            }
        }

        // Check request header
        if (true === $request->headers->has('authorization')) {
            $header = $request->headers->get('authorization');

            if (0 === strpos($header, 'Token')) {
                if ($token = $this->validateJwtToken(str_replace('Token ', '', $header))) {
                    return $token->user;
                }
            }
        }

        // Fallback
        return false;
    }

    public function createJwtTokenForUser($username)
    {
        $payload = [
            'iss' => $this->options['token_issuer'],
            'iat' => mktime(),
            'user' => $username,
        ];

        $token = JWT::encode(
            $payload,
            $this->options['token_secret_key']
        );

        return $token;
    }

    public function createCookieForToken($token)
    {
        return new Cookie(
            'TOKEN',
            $token,
            $this->options['cookie_lifetime'] + mktime(),
            $this->options['cookie_path'],
            $this->options['cookie_domain'],
            $this->options['cookie_secure'],
            $this->options['cookie_httponly']
        );
    }

    public function deleteCookie()
    {
        return new Cookie(
            'TOKEN',
            'deleted',
            1,
            $this->options['cookie_path'],
            $this->options['cookie_domain'],
            $this->options['cookie_secure'],
            $this->options['cookie_httponly']
        );
    }
}
