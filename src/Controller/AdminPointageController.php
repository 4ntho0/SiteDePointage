<?php

namespace App\Controller;

use App\Repository\PointageRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        $userFilter = $request->query->get('user');

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

    #[Route('/admin/edit', name: 'admin.pointage.edit', methods: ['POST'])]
    public function edit(Request $request, PointageRepository $repository, EntityManagerInterface $em): Response {
        $id = $request->request->get('id');
        $pointage = $repository->find($id);

        if (!$pointage) {
            $this->addFlash('error', 'Pointage introuvable.');
            return $this->redirectToRoute('admin.pointage');
        }

        // Mise à jour des champs
        $pointage->setDatePointage(new \DateTime($request->request->get('datePointage')));

        $heureEntree = $request->request->get('heureEntree');
        $pointage->setHeureEntree($heureEntree ? new \DateTime($heureEntree) : null);

        $heureDebutPause = $request->request->get('heureDebutPause');
        $pointage->setHeureDebutPause($heureDebutPause ? new \DateTime($heureDebutPause) : null);

        $heureFinPause = $request->request->get('heureFinPause');
        $pointage->setHeureFinPause($heureFinPause ? new \DateTime($heureFinPause) : null);

        $heureSortie = $request->request->get('heureSortie');
        $pointage->setHeureSortie($heureSortie ? new \DateTime($heureSortie) : null);

        $em->flush();

        $this->addFlash('success', 'Pointage mis à jour avec succès.');
        return $this->redirectToRoute('admin.pointage');
    }
}
