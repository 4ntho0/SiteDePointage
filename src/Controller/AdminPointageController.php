<?php

namespace App\Controller;

use App\Repository\PointageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPointageController extends AbstractController {

    private PointageRepository $repository;

    public function __construct(PointageRepository $repository) {
        $this->repository = $repository;
    }

    #[Route('/admin', name: 'admin.pointage')]
    public function index(Request $request): Response {
        $sortField = $request->query->get('sort', 'datePointage');
        $sortOrder = $request->query->get('order', 'DESC');
        $userFilter = $request->query->get('user'); // null si pas de filtre

        $pointages = $this->repository->findAllOrderByField($sortField, $sortOrder, $userFilter);

        $users = $this->repository->getAllUsernames();

        return $this->render('admin/admin.twig', [
                    'pointages' => $pointages,
                    'sortField' => $sortField,
                    'sortOrder' => $sortOrder,
                    'userFilter' => $userFilter,
                    'users' => $users,
        ]);
    }

    #[Route('/admin/suppr/{id}', name: 'admin.pointage.suppr')]
    public function suppr(int $id): Response {
        $pointage = $this->repository->find($id);
        if ($pointage) {
            $this->repository->remove($pointage);
        }

        return $this->redirectToRoute('admin.pointage');
    }
}
