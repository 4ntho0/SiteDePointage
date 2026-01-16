<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Routing\RouterInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    // ⚡ Signature corrigée avec type Request et type de retour Response
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $roles = $token->getRoleNames(); // récupère les rôles de l'utilisateur

        if (in_array('ROLE_ADMIN', $roles, true)) {
            $redirectUrl = $this->router->generate('admin.pointage'); // route admin
        } else {
            $redirectUrl = $this->router->generate('accueil'); // route utilisateur normal
        }

        return new RedirectResponse($redirectUrl);
    }
}
