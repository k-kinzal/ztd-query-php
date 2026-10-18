<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\{RequestEvent,ResponseEvent};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\{CsrfToken,CsrfTokenManagerInterface};

final class ApiSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CsrfTokenManagerInterface $tokens)
    {
    }
    public static function getSubscribedEvents(): array
    {
        return ['kernel.request' => ['check',10],'kernel.response' => 'headers'];
    }
    public function check(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/api/') && !$request->isMethodSafe() && !$this->tokens->isTokenValid(new CsrfToken('api', $request->headers->get('X-CSRF-Token', '')))) {
            throw new AccessDeniedHttpException('The CSRF token is missing or expired.');
        }
    }
    public function headers(ResponseEvent $event): void
    {
        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        if (str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            $headers->set('Cache-Control', 'private, no-store');
        }
    }
}
