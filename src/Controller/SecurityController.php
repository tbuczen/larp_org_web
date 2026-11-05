<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SiteSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, SiteSettingsRepository $settings): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        // EasyAdmin's login page, so the login screen matches the rest of the admin panel.
        return $this->render('@EasyAdmin/page/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'translation_domain' => 'admin',
            'page_title' => $settings->get()->getOrganizationName(),
            'csrf_token_intention' => 'authenticate',
            'target_path' => $this->generateUrl('admin'),
            'username_label' => 'login.email',
            'password_label' => 'login.password',
            'sign_in_label' => 'login.sign_in',
            'remember_me_enabled' => true,
            'remember_me_checked' => true,
            'remember_me_label' => 'login.remember_me',
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the logout key on the firewall.');
    }
}
