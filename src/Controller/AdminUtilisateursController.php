<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUtilisateursController extends AbstractController {

    #[Route('admin/utilisateurs', name: 'admin.utilisateurs')]
    public function index(UserRepository $userRepository): Response {
        $users = $userRepository->findAll();

        return $this->render('admin/admin.utilisateurs.html.twig', [
                    'users' => $users,
        ]);
    }

    #[Route('/admin/utilisateurs/add', name: 'admin.utilisateurs.add', methods: ['POST'])]
    public function add(
            Request $request,
            EntityManagerInterface $em,
            UserPasswordHasherInterface $passwordHasher
    ): RedirectResponse {
        $username = $request->request->get('username');
        $plainPassword = $request->request->get('password');
        $passwordConfirm = $request->request->get('password_confirm');
        $role = $request->request->get('role', 'ROLE_USER');

        $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN'];
        if (!in_array($role, $allowedRoles, true)) {
            $this->addFlash('error', 'Rôle invalide.');
            return $this->redirectToRoute('admin.utilisateurs');
        }


        if (!$username || !$plainPassword) {
            $this->addFlash('error', 'Nom d’utilisateur et mot de passe requis.');
            return $this->redirectToRoute('admin.utilisateurs');
        }

        if ($plainPassword !== $passwordConfirm) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('admin.utilisateurs');
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $user->setRoles([$role]);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Utilisateur créé avec succès.');
        return $this->redirectToRoute('admin.utilisateurs');
    }

    #[Route('/admin/utilisateurs/delete/{id}', name: 'admin.utilisateurs.delete', methods: ['POST'])]
    public function delete(User $user, EntityManagerInterface $em, Request $request): RedirectResponse {
        $submittedToken = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), $submittedToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin.utilisateurs');
        }

        $em->remove($user);
        $em->flush();
        return $this->redirectToRoute('admin.utilisateurs');
    }
}
