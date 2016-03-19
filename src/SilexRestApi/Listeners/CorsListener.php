<?php

namespace SilexRestApi\Listeners;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsListener implements EventSubscriberInterface
{
    /**
     * Simple headers as defined in the spec should always be accepted
     */
    protected static $simpleHeaders = [
        'accept',
        'accept-language',
        'content-language',
        'origin',
    ];
    protected $dispatcher;
    protected $defaults;

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => array('onKernelRequest', 10000),
            KernelEvents::RESPONSE => array('onKernelResponse'),
        ];
    }

    public function __construct(array $defaults = array())
    {
        if (isset($defaults['allow_headers'])) {
            $defaults['allow_headers'] = array_map('strtolower', $defaults['allow_headers']);
        }

        $this->defaults = $defaults;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $request = $event->getRequest();

        // skip if not a CORS request
        if (!$request->headers->has('Origin')) {
            return;
        }

        // perform preflight checks
        if ('OPTIONS' === $request->getMethod()) {
            $event->setResponse($this->getPreflightResponse($request));

            return;
        }

        if (!$this->isOriginAllowed($request)) {
            $response = new Response('', 403, array('Access-Control-Allow-Origin' => 'null'));
            $event->setResponse($response);

            return;
        }

        return;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

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
        // check origin
        $origin = $request->headers->get('Origin');

        if ($this->defaults['allow_origin'] === true || in_array($origin, $this->defaults['allow_origin'])) {
            return true;
        }

        return false;
    }
}
