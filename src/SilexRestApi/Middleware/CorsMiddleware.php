<?php

namespace SilexRestApi\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    protected static $simpleHeaders = [
        'accept',
        'accept-language',
        'content-language',
        'origin',
    ];

    protected $defaults;

    public function __construct(array $defaults = array())
    {
        if (isset($defaults['allow_headers'])) {
            $defaults['allow_headers'] = array_map('strtolower', $defaults['allow_headers']);
        }

        $this->defaults = $defaults;
    }

    public function processRequest(Request $request)
    {
        if (!$request->headers->has('Origin')) {
            return; // skip if not a cors request
        }

        if ('OPTIONS' === $request->getMethod()) {
            return $this->getPreflightResponse($request);
        }

        if (!$this->isOriginAllowed($request)) {
            return new Response('', 403, array('Access-Control-Allow-Origin' => 'null'));
        }
    }

    public function processResponse(Request $request, Response $response)
    {
        if ($response->headers->has('Access-Control-Allow-Origin') && $response->headers->get('Access-Control-Allow-Origin') === 'null') {
            return;
        }

        // add CORS response headers
        $response->headers->set('Access-Control-Allow-Origin', $this->defaults['allow_origin'] === true ? '*' : $request->headers->get('Origin'));

        if ($this->defaults['allow_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->defaults['expose_headers']) {
            $response->headers->set('Access-Control-Expose-Headers', strtolower(implode(', ', $this->defaults['expose_headers'])));
        }
    }

    protected function getPreflightResponse($request)
    {
        $response = new Response();

        if ($this->defaults['allow_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->defaults['allow_methods']) {
            $response->headers->set('Access-Control-Allow-Methods', strtoupper(implode(', ', $this->defaults['allow_methods'])));
        }

        if ($this->defaults['allow_headers']) {
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->defaults['allow_headers']));
        }

        if ($this->defaults['max_age']) {
            $response->headers->set('Access-Control-Max-Age', $this->defaults['max_age']);
        }

        if (!$this->isOriginAllowed($request)) {
            $response->headers->set('Access-Control-Allow-Origin', 'null');

            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $this->defaults['allow_origin'] === true ? '*' : $request->headers->get('Origin'));

        // check request method
        if (!in_array($request->headers->get('Access-Control-Request-Method'), $this->defaults['allow_methods'], true)) {
            $response->setStatusCode(405);

            return $response;
        }

        // check request headers
        if ($headers = trim(strtolower($request->headers->get('Access-Control-Request-Headers')))) {
            foreach (preg_split('{, *}', $headers) as $header) {
                if (in_array($header, self::$simpleHeaders, true)) {
                    continue;
                }

                if (!in_array($header, $this->defaults['allow_headers'], true)) {
                    $response->setStatusCode(400);
                    $response->setContent('Unauthorized header '.$header);

                    break;
                }
            }
        }

        return $response;
    }

    protected function isOriginAllowed($request)
    {
        $origin = $request->headers->get('Origin');

        if ($this->defaults['allow_origin'] === true || in_array($origin, $this->defaults['allow_origin'])) {
            return true;
        }

        return false;
    }
}
