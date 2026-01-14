<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/utilisateurs', name: 'admin.utilisateurs')]
class AdminUtilisateursController extends AbstractController
{
    #[Route('', name: '.index')]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('admin/admin.utilisateurs.html.twig', [
            'users' => $users,
        ]);
    }
}
