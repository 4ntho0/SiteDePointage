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
        // Définition du fuseau horaire
        $tz = new \DateTimeZone('Europe/Paris');

        // Début et fin de la journée
        $start = new \DateTimeImmutable('today', $tz);
        $end = $start->modify('+1 day');

        // Recherche d'une entrée en cours (sans heure de sortie)
        $entreeEnCours = $em->getRepository(Pointage::class)
                ->createQueryBuilder('p')
                ->where('p.datePointage >= :start')
                ->andWhere('p.datePointage < :end')
                ->andWhere('p.heureSortie IS NULL')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

        $hasEntree = $entreeEnCours !== null;

        // Récupération de l'heure d'embauche si elle existe
        $heureEntree = null;
        if ($entreeEnCours) {
            $heureEntree = $entreeEnCours->getHeureEntree()->format('H:i:s');
        }


        // Traitement du formulaire
        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');

            // ENTRÉE
            if ($type === 'entrée' && !$hasEntree) {
                $pointage = new Pointage();
                $now = new \DateTime('now', $tz);
                $pointage->setDatePointage($now);
                $pointage->setHeureEntree($now);
                $pointage->setHeureSortie(null);

                $em->persist($pointage);
            }

            // SORTIE
            if ($type === 'sortie' && $hasEntree) {
                $entreeEnCours->setHeureSortie(new \DateTime('now', $tz));
            }

            $em->flush();

            // PRG Pattern (évite le double POST)
            return $this->redirectToRoute('accueil');
        }

        return $this->render('pages/accueil.html.twig', [
                    'hasEntree' => $hasEntree,
            'heureEntree' => $heureEntree,
        ]);
    }
}
