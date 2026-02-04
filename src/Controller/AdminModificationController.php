<?php

// src/Controller/AdminModificationController.php

namespace App\Controller;

use App\Entity\Modification;
use App\Repository\ModificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminModificationController extends AbstractController {

    #[Route('/admin/modifications', name: 'admin.modifications')]
    // src/Controller/AdminModificationController.php
    public function index(ModificationRepository $repo, Request $request): Response {
        $typeFilter = $request->query->get('type', 'all');
        $actionFilter = $request->query->get('action', 'all');

        $qb = $repo->createQueryBuilder('m')->orderBy('m.date', 'DESC');

        // Filtre type
        if ($typeFilter !== 'all') {
            $qb->andWhere('m.type = :type')
                    ->setParameter('type', $typeFilter);
        }

        // Filtre action
        if ($actionFilter !== 'all') {
            if ($actionFilter === 'SUPPRESSION') {
                $qb->andWhere('m.action IN (:suppressions)')
                        ->setParameter('suppressions', [
                            'SUPPRESSION_UTILISATEUR',
                            'SUPPRESSION_POINTAGE'
                ]);
            } else {
                $qb->andWhere('m.action = :action')
                        ->setParameter('action', $actionFilter);
            }
        }

        $modifications = $qb->getQuery()->getResult();

        return $this->render('admin/admin.journal.modification.html.twig', [
                    'modifications' => $modifications,
                    'typeFilter' => $typeFilter,
                    'actionFilter' => $actionFilter,
        ]);
    }
}
