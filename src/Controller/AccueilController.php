<?php

namespace App\Controller;

use App\Entity\Pointage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccueilController extends AbstractController
{
    #[Route('/', name: 'accueil')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $tz = new \DateTimeZone('Europe/Paris');
        $now = new \DateTime('now', $tz);

        // Coordonnées de référence
        $LAT_REF = 46.8025344;
        $LNG_REF = 1.6744448;
        $RAYON_AUTORISE_METRES = 100;

        // Récupération géolocalisation
        $latitude = $request->request->get('latitude');
        $longitude = $request->request->get('longitude');

        // Période du jour
        $start = new \DateTimeImmutable('today', $tz);
        $end = $start->modify('+1 day');

        // Pointage du jour sans sortie
        $entreeEnCours = $em->getRepository(Pointage::class)
            ->createQueryBuilder('p')
            ->where('p.datePointage >= :start')
            ->andWhere('p.datePointage < :end')
            ->andWhere('p.heureSortie IS NULL')
            ->andWhere('p.utilisateur = :user')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('user', $this->getUser())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $hasEntree = $entreeEnCours !== null;

        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');

            // Géolocalisation requise
            if (!$latitude || !$longitude) {
                return $this->redirectToRoute('accueil');
            }

            // Vérification distance
            $distanceMetres = $this->calculerDistance(
                (float) $latitude,
                (float) $longitude,
                $LAT_REF,
                $LNG_REF
            );

            if ($distanceMetres > $RAYON_AUTORISE_METRES) {
                return $this->redirectToRoute('accueil');
            }

            switch ($type) {
                case 'entrée':
                    if (!$hasEntree) {
                        $pointage = new Pointage();
                        $pointage
                            ->setDatePointage($now)
                            ->setHeureEntree($now)
                            ->setLatitudeEntree($latitude)
                            ->setLongitudeEntree($longitude)
                            ->setUtilisateur($this->getUser());

                        $em->persist($pointage);
                    }
                    break;

                case 'pause':
                    if ($hasEntree && $entreeEnCours->getHeureDebutPause() === null) {
                        $entreeEnCours->setHeureDebutPause($now);
                    }
                    break;

                case 'reprise':
                    if (
                        $hasEntree &&
                        $entreeEnCours->getHeureDebutPause() !== null &&
                        $entreeEnCours->getHeureFinPause() === null
                    ) {
                        $entreeEnCours->setHeureFinPause($now);
                    }
                    break;

                case 'sortie':
                    if ($hasEntree) {
                        $entreeEnCours
                            ->setHeureSortie($now)
                            ->setLatitudeSortie($latitude)
                            ->setLongitudeSortie($longitude);
                    }
                    break;
            }

            $em->flush();

            return $this->redirectToRoute('accueil');
        }

        $heureEntree = $hasEntree
            ? $entreeEnCours->getHeureEntree()?->format('H:i:s')
            : null;

        $heureDebutPause = $entreeEnCours?->getHeureDebutPause();
        $heureFinPause = $entreeEnCours?->getHeureFinPause();

        $enPause = $heureDebutPause !== null && $heureFinPause === null;
        $pauseTerminee = $heureDebutPause !== null && $heureFinPause !== null;

        return $this->render('pages/accueil.html.twig', [
            'hasEntree'       => $hasEntree,
            'heureEntree'     => $heureEntree,
            'enPause'         => $enPause,
            'pauseTerminee'   => $pauseTerminee,
            'heureDebutPause' => $heureDebutPause?->format('H:i:s'),
            'heureFinPause'   => $heureFinPause?->format('H:i:s'),
        ]);
    }

    private function calculerDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371000; // m

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a =
            sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
