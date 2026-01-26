<?php

namespace App\Controller;

use App\Repository\PointageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

class AdminPointageController extends AbstractController
{
    private PointageRepository $repository;

    public function __construct(PointageRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/admin/pointages', name: 'admin.pointage', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $sortField = $request->query->get('sort', 'datePointage');
        $sortOrder = $request->query->get('order', 'DESC');
        $userFilter = $request->query->get('user');
        $userFilters = $userFilter ? explode(',', $userFilter) : [];
        $page = max(1, (int) $request->query->get('page', 1));

        [$dateStart, $dateEnd, $period] = $this->getDateRangeFromRequest($request);

        $pointages = $this->repository->findAllOrderByFieldWithLimit(
            $sortField,
            $sortOrder,
            $userFilter,
            $dateStart,
            $dateEnd
        );

        $totalPointages = $this->repository->countFiltered($userFilter, $dateStart, $dateEnd);
        $users = $this->repository->getAllUsernames();

        return $this->render('admin/admin.pointages.html.twig', [
            'pointages' => $pointages,
            'users' => $users,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'userFilter' => $userFilter,
            'userFilters' => $userFilters,
            'page' => $page,
            'totalPointages' => $totalPointages,
            'period' => $period,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'dateStartInput' => $request->query->get('date_start'),
            'dateEndInput' => $request->query->get('date_end'),
        ]);
    }

    #[Route('/admin/pointages/suppr/{id}', name: 'admin.pointage.suppr', methods: ['GET'])]
    public function suppr(int $id, PointageRepository $repository): Response
    {
        $pointage = $repository->find($id);
        if ($pointage) {
            $repository->remove($pointage);
        }
        return $this->redirectToRoute('admin.pointage');
    }

    #[Route('/admin/pointages/edit', name: 'admin.pointage.edit', methods: ['POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
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

    #[Route('/admin/pointages/export/pdf', name: 'admin.pointage.export.pdf')]
    public function exportPdf(Request $request): Response
    {
        $sortField = $request->query->get('sort', 'datePointage');
        $sortOrder = $request->query->get('order', 'DESC');
        $userFilter = $request->query->get('user');

        [$dateStart, $dateEnd, $period] = $this->getDateRangeFromRequest($request);

        $pointages = $this->repository->findAllOrderByFieldWithLimit(
            $sortField,
            $sortOrder,
            $userFilter,
            $dateStart,
            $dateEnd
        );

        $html = $this->renderView('admin/_pointages_pdf.html.twig', [
            'pointages' => $pointages,
            'userFilter' => $userFilter,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="pointages_' . date('Y-m-d') . '.pdf"'
            ]
        );
    }

    private function getDateRangeFromRequest(Request $request): array
    {
        $period = $request->query->get('period', 'week');
        $page = max(1, (int) $request->query->get('page', 1));

        $dateStartInput = $request->query->get('date_start');
        $dateEndInput = $request->query->get('date_end');

        $dateStart = null;
        $dateEnd = null;

        $today = new \DateTimeImmutable('today');

        if ($dateStartInput && $dateEndInput) {
            // Dates personnalisées
            $dateStart = new \DateTimeImmutable($dateStartInput);
            $dateEnd = new \DateTimeImmutable($dateEndInput);
        } else {
            switch ($period) {
                case 'day':
                    $date = $today->modify('-' . ($page - 1) . ' days');
                    $dateStart = $date;
                    $dateEnd = $date;
                    break;

                case 'week':
                    $currentWeekStart = $today->modify('monday this week');
                    $currentWeekStart = $currentWeekStart->modify('-' . ($page - 1) . ' weeks');
                    $dateStart = $currentWeekStart;
                    $dateEnd = $currentWeekStart->modify('sunday this week');
                    break;

                case 'month':
                    $currentMonth = $today->modify('first day of this month');
                    $targetMonth = $currentMonth->modify('-' . ($page - 1) . ' months');
                    $dateStart = $targetMonth;
                    $dateEnd = $targetMonth->modify('last day of this month');
                    break;

                case 'year':
                    $yearOffset = (int) $today->format('Y') - ($page - 1);
                    $dateStart = new \DateTimeImmutable($yearOffset . '-01-01');
                    $dateEnd = new \DateTimeImmutable($yearOffset . '-12-31');
                    break;

                case 'global':
                default:
                    $dateStart = null;
                    $dateEnd = null;
                    break;
            }
        }

        return [$dateStart, $dateEnd, $period];
    }
}
