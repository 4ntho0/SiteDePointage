<?php

namespace App\Controller;

use App\Entity\Pointage;
use App\Repository\PointageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPointageController extends AbstractController
{
    private PointageRepository $repository;

    public function __construct(PointageRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/admin', name: 'admin.pointage')]
    public function index(): Response
    {
        // On récupère tous les pointages triés par date décroissante
        $pointages = $this->repository->findAllOrderBy('datePointage', 'DESC');

        return $this->render('admin/admin.twig', [
            'pointages' => $pointages
        ]);
    }
}
