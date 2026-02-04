<?php

namespace App\Controller;

use App\Repository\PointageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPointageListController extends AbstractController
{
    private const CHAMP_DATE_POINTAGE = 'datePointage';

    private PointageRepository $repository;

    public function __construct(PointageRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/admin/pointages', name: 'admin.pointage', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $sortField = $request->query->get('sort', self::CHAMP_DATE_POINTAGE);
        $sortOrder = $request->query->get('order', 'DESC');
        $userFilter = $request->query->get('user');
        $userFilters = $userFilter ? explode(',', $userFilter) : [];
        $page = max(1, (int) $request->query->get('page', 1));

        [$dateStart, $dateEnd, $period] = $this->getDateRangeFromRequest($request);

        $pointages = $this->repository->findAllOrderByFieldWithLimit(
            $sortField, $sortOrder, $userFilter, $dateStart, $dateEnd
        );

        $recapParUtilisateur = $this->repository->getRecapParUtilisateur($dateStart, $dateEnd, $userFilter);
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
            'recapParUtilisateur' => $recapParUtilisateur,
        ]);
    }

    private function getDateRangeFromRequest(Request $request): array
    {
        $period = $request->query->get('period', 'week');
        $page = max(1, (int) $request->query->get('page', 1));
        $today = new \DateTimeImmutable('today');

        if ($request->query->has('date_start') && $request->query->has('date_end')) {
            return [
                new \DateTimeImmutable($request->query->get('date_start')),
                new \DateTimeImmutable($request->query->get('date_end')),
                $period
            ];
        }

        return match ($period) {
            'day' => $this->getDayRange($today, $page),
            'week' => $this->getWeekRange($today, $page),
            'month' => $this->getMonthRange($today, $page),
            'year' => $this->getYearRange($today, $page),
            default => [null, null, $period]
        };
    }

    private function getDayRange(\DateTimeImmutable $today, int $page): array
    {
        $date = $today->modify('-' . ($page - 1) . ' days');
        return [$date, $date, 'day'];
    }

    private function getWeekRange(\DateTimeImmutable $today, int $page): array
    {
        $weekStart = $today->modify('monday this week')->modify('-' . ($page - 1) . ' weeks');
        $weekEnd = $weekStart->modify('sunday this week');
        return [$weekStart, $weekEnd, 'week'];
    }

    private function getMonthRange(\DateTimeImmutable $today, int $page): array
    {
        $monthStart = $today->modify('first day of this month')->modify('-' . ($page - 1) . ' months');
        $monthEnd = $monthStart->modify('last day of this month');
        return [$monthStart, $monthEnd, 'month'];
    }

    private function getYearRange(\DateTimeImmutable $today, int $page): array
    {
        $year = (int) $today->format('Y') - ($page - 1);
        return [
            new \DateTimeImmutable($year . '-01-01'),
            new \DateTimeImmutable($year . '-12-31'),
            'year'
        ];
    }
}
