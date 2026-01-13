<?php

namespace App\Controller;

use App\Entity\Pointage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccueilController extends AbstractController {

    #[Route('/', name: 'accueil')]
    public function index(Request $request, EntityManagerInterface $em): Response {
        $tz = new \DateTimeZone('Europe/Paris');
        $now = new \DateTime('now', $tz);

        // Journée courante
        $start = new \DateTimeImmutable('today', $tz);
        $end = $start->modify('+1 day');

        // Pointage en cours
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

        // Traitement du post
        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');

            switch ($type) {
                case 'entrée':
                    if (!$hasEntree) {
                        $pointage = new Pointage();
                        $pointage
                                ->setDatePointage($now)
                                ->setHeureEntree($now)
                                ->setHeureSortie(null)
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
                        $entreeEnCours->setHeureSortie($now);
                    }
                    break;
            }

            $em->flush();

            // PRG pattern
            return $this->redirectToRoute('accueil');
        }

        // états des pauses
        $heureEntree = $hasEntree ? $entreeEnCours->getHeureEntree()?->format('H:i:s') : null;

        $heureDebutPause = $entreeEnCours?->getHeureDebutPause();
        $heureFinPause = $entreeEnCours?->getHeureFinPause();

        $enPause = $heureDebutPause !== null && $heureFinPause === null;
        $pauseTerminee = $heureDebutPause !== null && $heureFinPause !== null;

        return $this->render('pages/accueil.html.twig', [
                    'hasEntree' => $hasEntree,
                    'heureEntree' => $heureEntree,
                    'enPause' => $enPause,
                    'pauseTerminee' => $pauseTerminee,
                    'heureDebutPause' => $heureDebutPause?->format('H:i:s'),
                    'heureFinPause' => $heureFinPause?->format('H:i:s'),
        ]);
    }
}
