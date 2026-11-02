<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * Toont het inlogformulier. De POST-naar-deze-pagina wordt
     * afgevangen door de "form_login"-firewall (check_path = /login).
     */
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authUtils, Request $request): Response
    {
        if ('legacy_app_login' === $request->attributes->get('_route')) {
            return $this->redirectToRoute('app_login', ['_locale' => 'nl'], Response::HTTP_TEMPORARY_REDIRECT);
        }
        return $this->render('security/login.html.twig', [
            // Bij een mislukte inlogproblematie vult AuthenticationUtils
            // error en last_username in; anders zijn ze null.
            'error' => $authUtils->getLastAuthenticationError(),
            'last_username' => $authUtils->getLastUsername(),
        ]);
    }

    /**
     * Wordt nooit bereikt: de "logout"-firewall intercepteert /logout.
     */
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        if ('legacy_app_logout' === $request->attributes->get('_route')) {
            return $this->redirectToRoute('app_logout', ['_locale' => 'nl'], Response::HTTP_TEMPORARY_REDIRECT);
        }

        throw new \LogicException('Uitloggen wordt afgehandeld door de security-firewall.');
    }
}
