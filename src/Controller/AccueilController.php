<?php

namespace App\Controller;

use App\Entity\Pointage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use DateTimeZone;

class AccueilController extends AbstractController {

    #[Route('/', name: 'accueil')]
    public function index(Request $request, EntityManagerInterface $em): Response {
        $tz = new DateTimeZone('Europe/Paris');
        $now = new DateTime('now', $tz);

        // Early return pour POST
        if ($request->isMethod('POST')) {
            return $this->handlePointagePost($request, $em, $now);
        }

        // GET : affichage
        $entreeEnCours = $this->getPointageEnCours($em, $tz);

        return $this->render('pages/accueil.html.twig', $this->prepareTemplateData($entreeEnCours));
    }

    private function handlePointagePost(Request $request, EntityManagerInterface $em, DateTime $now): Response {
        // Validation géolocalisation
        if (!$this->isGeolocalisationValide($request)) {
            return $this->redirectToRoute('accueil');
        }

        // Vérification distance
        if (!$this->isDansRayonAutorise($request)) {
            return $this->redirectToRoute('accueil');
        }

        // ✅ Récupérer pointage en cours
        $tz = new DateTimeZone('Europe/Paris');
        $entreeEnCours = $this->getPointageEnCours($em, $tz);

        // Traitement selon type
        $this->traiterActionPointage(
                $request->request->get('type'),
                $entreeEnCours,
                $now,
                $request->request->get('latitude'),
                $request->request->get('longitude'),
                $em
        );

        $em->flush();
        return $this->redirectToRoute('accueil');
    }

    private function isGeolocalisationValide(Request $request): bool {
        return $request->request->has('latitude') && $request->request->has('longitude');
    }

    private function isDansRayonAutorise(Request $request): bool {
        $latitude = (float) $request->request->get('latitude');
        $longitude = (float) $request->request->get('longitude');

        $distance = $this->calculerDistance(
                $latitude, $longitude,
                46.8034232, 1.6719998  // Coordonnées fixes
        );

        return $distance <= 500; // Rayon autorisé
    }

    private function traiterActionPointage(
            string $type,
            ?Pointage $entreeEnCours,
            DateTime $now,
            string $latitude,
            string $longitude,
            EntityManagerInterface $em
    ): void {
        match ($type) {
            'entrée' => $this->actionEntree($em, $now, $latitude, $longitude),
            'pause' => $this->actionPause($entreeEnCours, $now),
            'reprise' => $this->actionReprise($entreeEnCours, $now),
            'sortie' => $this->actionSortie($entreeEnCours, $now, $latitude, $longitude),
        };
    }

    private function getPointageEnCours(EntityManagerInterface $em, DateTimeZone $tz): ?Pointage {
        $user = $this->getUser();
        $today = new DateTime('today', $tz);

        return $em->createQueryBuilder()
                        ->select('p')
                        ->from(Pointage::class, 'p')
                        ->where('p.utilisateur = :user')
                        ->andWhere('p.datePointage = :today')
                        ->andWhere('p.heureSortie IS NULL')  // ✅ CLÉ : ignore les pointages terminés
                        ->setParameter('user', $user)
                        ->setParameter('today', $today)
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
    }

    private function prepareTemplateData(?Pointage $entreeEnCours): array {
        $hasEntree = $entreeEnCours !== null;
        $enPause = $hasEntree &&
                $entreeEnCours->getHeureDebutPause() &&
                !$entreeEnCours->getHeureFinPause();
        $pauseTerminee = $hasEntree &&
                $entreeEnCours->getHeureFinPause() !== null;

        return [
            'entreeEnCours' => $entreeEnCours,
            'user' => $this->getUser(),
            'hasEntree' => $hasEntree,
            'heureEntree' => $entreeEnCours?->getHeureEntree() ? $entreeEnCours->getHeureEntree()->format('H:i:s') : null,
            'enPause' => $enPause,
            'pauseTerminee' => $pauseTerminee,
        ];
    }

    private function calculerDistance(float $lat1, float $long1, float $lat2, float $long2): float {
        $earthRadius = 6371000; // Rayon Terre (mètres)

        $lat1Rad = deg2rad($lat1);
        $long1Rad = deg2rad($long1);
        $lat2Rad = deg2rad($lat2);
        $long2Rad = deg2rad($long2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLong = $long2Rad - $long1Rad;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
                cos($lat1Rad) * cos($lat2Rad) *
                sin($deltaLong / 2) * sin($deltaLong / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function actionEntree(EntityManagerInterface $em, DateTime $now, string $latitude, string $longitude): void {
        $pointage = new Pointage();
        $pointage->setDatePointage($now);
        $pointage->setHeureEntree($now);
        $pointage->setLatitudeEntree($latitude);
        $pointage->setLongitudeEntree($longitude);
        $pointage->setUtilisateur($this->getUser());

        $em->persist($pointage);
    }

    private function actionPause(?Pointage $entreeEnCours, DateTime $now): void {
        if ($entreeEnCours) {
            $entreeEnCours->setHeureDebutPause($now);
        }
    }

    private function actionReprise(?Pointage $entreeEnCours, DateTime $now): void {
        if ($entreeEnCours) {
            $entreeEnCours->setHeureFinPause($now);
        }
    }

    private function actionSortie(?Pointage $entreeEnCours, DateTime $now, string $latitude, string $longitude): void {
        if ($entreeEnCours) {
            $entreeEnCours->setHeureSortie($now);
            $entreeEnCours->setLatitudeSortie($latitude);
            $entreeEnCours->setLongitudeSortie($longitude);
        }
    }
}
