<?php

namespace App\Controller;

use App\Entity\Pointage;
use App\Entity\Modification;
use App\Repository\PointageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPointageCrudController extends AbstractController {

    private const TYPE_POINTAGE = 'pointage';
    private const ACTION_SUPPRESSION = 'SUPPRESSION_POINTAGE';
    private const ACTION_MODIFICATION = 'MODIFICATION_POINTAGE';
    private const ROUTE_ADMIN_POINTAGE = 'admin.pointage';
    private const FORMAT_DATE_JOUR = 'Y-m-d';
    private const FORMAT_HEURE_MINUTES = 'H:i';

    private PointageRepository $repository;

    public function __construct(PointageRepository $repository) {
        $this->repository = $repository;
    }

    #[Route('/admin/pointages/suppr/{id}', name: 'admin.pointage.suppr', methods: ['GET'])]
    public function suppr(int $id, PointageRepository $repository, EntityManagerInterface $em): Response {
        $pointage = $repository->find($id);
        if (!$pointage) {
            return $this->redirectToRoute(self::ROUTE_ADMIN_POINTAGE);
        }

        $this->loggerSuppression($em, $pointage);
        $repository->remove($pointage);
        $em->flush();

        return $this->redirectToRoute(self::ROUTE_ADMIN_POINTAGE);
    }

    #[Route('/admin/pointages/edit', name: 'admin.pointage.edit', methods: ['POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response {
        $pointage = $this->repository->find($request->request->get('id'));
        if (!$pointage) {
            return $this->redirectToRoute(self::ROUTE_ADMIN_POINTAGE);
        }

        $oldData = $this->serializePointage($pointage);
        if (!$this->isFormulaireValide($request)) {
            return $this->redirectToRoute(self::ROUTE_ADMIN_POINTAGE);
        }

        $this->mettreAJourPointage($pointage, $request);
        $em->flush();
        $this->loggerModification($em, $pointage, $oldData);

        return $this->redirectToRoute(self::ROUTE_ADMIN_POINTAGE);
    }

    private function serializePointage(Pointage $p): array {
        return [
            'date' => $p->getDatePointage()?->format(self::FORMAT_DATE_JOUR),
            'entree' => $p->getHeureEntree()?->format(self::FORMAT_HEURE_MINUTES),
            'debut_pause' => $p->getHeureDebutPause()?->format(self::FORMAT_HEURE_MINUTES),
            'fin_pause' => $p->getHeureFinPause()?->format(self::FORMAT_HEURE_MINUTES),
            'sortie' => $p->getHeureSortie()?->format(self::FORMAT_HEURE_MINUTES),
            'total' => $p->getTotalTravailFormatted() ?? null,
            'user' => $p->getUtilisateur()?->getUsername(),
        ];
    }

    private function isFormulaireValide(Request $request): bool {
        $donnees = $this->extraireDonneesFormulaire($request);
        if (($donnees['debut'] && !$donnees['fin']) || (!$donnees['debut'] && $donnees['fin'])) {
            return false;
        }
        return !$this->aDesIncoherencesHoraires($donnees);
    }

    private function extraireDonneesFormulaire(Request $request): array {
        return [
            'date' => $request->request->get('datePointage'),
            'entree' => $request->request->get('heureEntree'),
            'debut' => $request->request->get('heureDebutPause'),
            'fin' => $request->request->get('heureFinPause'),
            'sortie' => $request->request->get('heureSortie'),
        ];
    }

    private function aDesIncoherencesHoraires(array $donnees): bool {
        $dates = $this->convertirEnDateTime($donnees);
        return $dates['entree'] && $dates['debut'] && $dates['entree'] > $dates['debut'] ||
                $dates['debut'] && $dates['fin'] && $dates['debut'] > $dates['fin'] ||
                $dates['fin'] && $dates['sortie'] && $dates['fin'] > $dates['sortie'] ||
                $dates['entree'] && $dates['sortie'] && $dates['entree'] > $dates['sortie'];
    }

    private function convertirEnDateTime(array $donnees): array {
        return [
            'entree' => $donnees['entree'] ? new \DateTime($donnees['entree']) : null,
            'debut' => $donnees['debut'] ? new \DateTime($donnees['debut']) : null,
            'fin' => $donnees['fin'] ? new \DateTime($donnees['fin']) : null,
            'sortie' => $donnees['sortie'] ? new \DateTime($donnees['sortie']) : null,
        ];
    }

    private function mettreAJourPointage(Pointage $pointage, Request $request): void {
        $pointage->setDatePointage(new \DateTime($request->request->
                get('datePointage')));
        $pointage->setHeureEntree($request->request->
                get('heureEntree') ? new \DateTime($request->request->
                        get('heureEntree')) : null);
        $pointage->setHeureDebutPause($request->request->
                get('heureDebutPause') ? new \DateTime($request->request->
                        get('heureDebutPause')) : null);
        $pointage->setHeureFinPause($request->request->
                get('heureFinPause') ? new \DateTime($request->request->
                        get('heureFinPause')) : null);
        $pointage->setHeureSortie($request->request->
                get('heureSortie') ? new \DateTime($request->request->
                        get('heureSortie')) : null);
    }

    private function loggerModification(EntityManagerInterface $em, Pointage $pointage, array $oldData): void {
        $newData = $this->serializePointage($pointage);
        $log = new Modification();
        $log->setDate(new \DateTime());
        $log->setType(self::TYPE_POINTAGE);
        $log->setAction(self::ACTION_MODIFICATION);
        $log->setObjetId($pointage->getId());
        $log->setAnciennesDonnees($oldData);
        $log->setNouvellesDonnees($newData);
        $em->persist($log);
        $em->flush();
    }

    private function loggerSuppression(EntityManagerInterface $em, Pointage $pointage): void {
        $oldData = $this->serializePointage($pointage);
        $log = new Modification();
        $log->setDate(new \DateTime());
        $log->setType(self::TYPE_POINTAGE);
        $log->setAction(self::ACTION_SUPPRESSION);
        $log->setObjetId($pointage->getId());
        $log->setAnciennesDonnees($oldData);
        $log->setNouvellesDonnees(null);
        $em->persist($log);
    }
}
