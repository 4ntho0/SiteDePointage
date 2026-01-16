<?php

namespace App\Controller;

use App\Repository\PointageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class AdminPointageController extends AbstractController {

    private PointageRepository $repository;

    public function __construct(PointageRepository $repository) {
        $this->repository = $repository;
    }

    #[Route('/admin/pointages', name: 'admin.pointage')]
    public function index(Request $request): Response {
        $sortField = $request->query->get('sort', 'datePointage');
        $sortOrder = $request->query->get('order', 'DESC');
        $userFilter = $request->query->get('user');
        $limit = (int) $request->query->get('limit', 25);
        $page = max(1, (int) $request->query->get('page', 1)); // page >= 1
        // Nombre total de pointages filtrés
        $totalPointages = count($this->repository->findAllOrderByField($sortField, $sortOrder, $userFilter));

        // Calcul offset
        $offset = ($page - 1) * $limit;

        // Récupérer uniquement les pointages pour cette page
        $pointages = $this->repository->findAllOrderByFieldWithLimit($sortField, $sortOrder, $userFilter, $limit, $offset);

        $users = $this->repository->getAllUsernames();

        $totalPages = ceil($totalPointages / $limit);

        return $this->render('admin/admin.pointages.html.twig', [
                    'pointages' => $pointages,
                    'users' => $users,
                    'sortField' => $sortField,
                    'sortOrder' => $sortOrder,
                    'userFilter' => $userFilter,
                    'limit' => $limit,
                    'page' => $page,
                    'totalPages' => $totalPages,
        ]);
    }

    #[Route('/admin/pointages/suppr/{id}', name: 'admin.pointage.suppr')]
    public function suppr(int $id): Response {
        $pointage = $this->repository->find($id);

        if ($pointage) {
            $this->repository->remove($pointage);
        }

        return $this->redirectToRoute('admin.pointage');
    }

    #[Route('/admin/pointages/edit', name: 'admin.pointage.edit', methods: ['POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response {
        $id = $request->request->get('id');
        $pointage = $this->repository->find($id);

        if (!$pointage) {
            return $this->redirectToRoute('admin.pointage');
        }

        $date = $request->request->get('datePointage');
        $entree = $request->request->get('heureEntree');
        $debut = $request->request->get('heureDebutPause');
        $fin = $request->request->get('heureFinPause');
        $sortie = $request->request->get('heureSortie');

        $entree = $entree ? new \DateTime($entree) : null;
        $debut = $debut ? new \DateTime($debut) : null;
        $fin = $fin ? new \DateTime($fin) : null;
        $sortie = $sortie ? new \DateTime($sortie) : null;

        if (($debut && !$fin) || (!$debut && $fin)) {
            return $this->redirectToRoute('admin.pointage');
        }

        if (
                ($entree && $debut && $entree > $debut) ||
                ($debut && $fin && $debut > $fin) ||
                ($fin && $sortie && $fin > $sortie) ||
                ($entree && $sortie && $entree > $sortie)
        ) {
            return $this->redirectToRoute('admin.pointage');
        }

        $pointage->setDatePointage(new \DateTime($date));
        $pointage->setHeureEntree($entree);
        $pointage->setHeureDebutPause($debut);
        $pointage->setHeureFinPause($fin);
        $pointage->setHeureSortie($sortie);

        $em->flush();

        return $this->redirectToRoute('admin.pointage');
    }
}
