<?php

namespace App\Controller;

use App\Repository\UserRepository;
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
        $roles = $request->request->get('roles', 'ROLE_USER');

        if (!$username || !$plainPassword) {
            $this->addFlash('error', 'Nom d’utilisateur et mot de passe requis.');
            return $this->redirectToRoute('admin.utilisateurs');
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $user->setRoles(array_map('trim', explode(',', $roles)));

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Utilisateur créé avec succès.');
        return $this->redirectToRoute('admin.utilisateurs');
    }
}
