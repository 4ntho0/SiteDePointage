<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserSecurityListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Skip login page et routes admin
        if ($request->attributes->get('_route') === 'app_login' ||
            str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // ✅ DÉCONNEXION si !isEnabled()
        if (!$user->isEnabled()) {
            $this->tokenStorage->setToken(null);
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_login')
            ));
        }
    }
}
