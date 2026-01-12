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
        if ($request->isMethod('POST')) {

            $type = $request->request->get('type');

            $start = new \DateTimeImmutable('today');
            $end = $start->modify('+1 day');

            if ($type === 'entrée') {
                $pointage = new Pointage();
                $pointage->setDatePointage(new \DateTime());
                $pointage->setHeureEntree(new \DateTime());
                $pointage->setHeureSortie(null);

                $em->persist($pointage);
            }

            if ($type === 'sortie') {
                $pointage = $em->getRepository(Pointage::class)
                    ->createQueryBuilder('p')
                    ->where('p.datePointage >= :start')
                    ->andWhere('p.datePointage < :end')
                    ->setParameter('start', $start)
                    ->setParameter('end', $end)
                    ->orderBy('p.id', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($pointage) {
                    $pointage->setHeureSortie(new \DateTime());
                }
            }

            $em->flush();
            return $this->redirectToRoute('accueil');
        }

        // Affichage
        $start = new \DateTimeImmutable('today');
        $end = $start->modify('+1 day');

        $pointages = $em->getRepository(Pointage::class)
            ->createQueryBuilder('p')
            ->where('p.datePointage >= :start')
            ->andWhere('p.datePointage < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('pages/accueil.html.twig', [
            'pointages' => $pointages
        ]);
    }
}
