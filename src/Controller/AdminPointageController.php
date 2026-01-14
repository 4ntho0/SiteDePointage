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

        $date = $request->request->get('datePointage');
        $heureEntree = $request->request->get('heureEntree');
        $heureDebutPause = $request->request->get('heureDebutPause');
        $heureFinPause = $request->request->get('heureFinPause');
        $heureSortie = $request->request->get('heureSortie');

        // Création des DateTime
        $entree = $heureEntree ? new \DateTime($heureEntree) : null;
        $debutPause = $heureDebutPause ? new \DateTime($heureDebutPause) : null;
        $finPause = $heureFinPause ? new \DateTime($heureFinPause) : null;
        $sortie = $heureSortie ? new \DateTime($heureSortie) : null;

        // Vérifier que la pause est complète ou vide
        if (($debutPause && !$finPause) || (!$debutPause && $finPause)) {
            $this->addFlash('error', 'Vous devez remplir à la fois le début et la fin de la pause ou laisser les deux vides.');
            return $this->redirectToRoute('admin.pointage');
        }


        // Vérification de la cohérence des horaires
        if ($entree && $debutPause && $entree > $debutPause) {
            $this->addFlash('error', "L'heure d'entrée doit être avant le début de la pause.");
            return $this->redirectToRoute('admin.pointage');
        }

        if ($debutPause && $finPause && $debutPause > $finPause) {
            $this->addFlash('error', "Le début de pause doit être avant la fin de la pause.");
            return $this->redirectToRoute('admin.pointage');
        }

        if ($sortie && $finPause && $finPause > $sortie) {
            $this->addFlash('error', "La fin de pause doit être avant l'heure de sortie.");
            return $this->redirectToRoute('admin.pointage');
        }

        if ($entree && $sortie && $entree > $sortie) {
            $this->addFlash('error', "L'heure d'entrée doit être avant l'heure de sortie.");
            return $this->redirectToRoute('admin.pointage');
        }

        // Mise à jour après validation
        $pointage->setDatePointage(new \DateTime($date));
        $pointage->setHeureEntree($entree);
        $pointage->setHeureDebutPause($debutPause);
        $pointage->setHeureFinPause($finPause);
        $pointage->setHeureSortie($sortie);

        $em->flush();

        $this->addFlash('success', 'Pointage mis à jour avec succès.');
        return $this->redirectToRoute('admin.pointage');
    }
}
