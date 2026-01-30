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

class AdminPointageController extends AbstractController {

    private PointageRepository $repository;

    public function __construct(PointageRepository $repository) {
        $this->repository = $repository;
    }

    #[Route('/admin/pointages', name: 'admin.pointage', methods: ['GET'])]
    public function index(Request $request): Response {
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

        $recapParUtilisateur = $this->repository->getRecapParUtilisateur(
                $dateStart,
                $dateEnd,
                $userFilter
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
                    'recapParUtilisateur' => $recapParUtilisateur,
        ]);
    }

    #[Route('/admin/pointages/suppr/{id}', name: 'admin.pointage.suppr', methods: ['GET'])]
    public function suppr(int $id, PointageRepository $repository): Response {
        $pointage = $repository->find($id);
        if ($pointage) {
            $repository->remove($pointage);
        }
        return $this->redirectToRoute('admin.pointage');
    }

    #[Route('/admin/pointages/edit', name: 'admin.pointage.edit', methods: ['POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response {
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
    public function exportPdf(Request $request): Response {
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

        $recapParUtilisateur = $this->repository->getRecapParUtilisateur(
                $dateStart,
                $dateEnd,
                $userFilter
        );

        $html = $this->renderView('admin/_pointages_pdf.html.twig', [
            'pointages' => $pointages,
            'recapParUtilisateur' => $recapParUtilisateur,
            'userFilter' => $userFilter,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'period' => $period
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

    private function getDateRangeFromRequest(Request $request): array {
        $period = $request->query->get('period', 'week');
        $page = max(1, (int) $request->query->get('page', 1));

        $dateStartInput = $request->query->get('date_start');
        $dateEndInput = $request->query->get('date_end');

        $dateStart = null;
        $dateEnd = null;

        $today = new \DateTimeImmutable('today');

        if ($dateStartInput && $dateEndInput) {
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

    #[Route('/admin/pointages/export/excel', name: 'admin.pointage.export.excel')]
    public function exportExcel(Request $request): Response {
        // Récupération des données
        $sortField = $request->query->get('sort', 'datePointage');
        $sortOrder = $request->query->get('order', 'DESC');
        $userFilter = $request->query->get('user');
        [$dateStart, $dateEnd, $period] = $this->getDateRangeFromRequest($request);

        $pointages = $this->repository->findAllOrderByFieldWithLimit($sortField, $sortOrder, $userFilter, $dateStart, $dateEnd);
        $recapParUtilisateur = $this->repository->getRecapParUtilisateur($dateStart, $dateEnd, $userFilter);

        $tempFile = sys_get_temp_dir() . '/pointages_' . uniqid() . '.xlsx';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pointages');

        //EN-TÊTE RAPPORT (lignes 1-2) - CENTRÉ
        $dateGeneration = (new \DateTime())->format('d/m/Y \à H:i');
        $periodeText = $period === 'global' ? 'Toute la période' :
                ($period === 'day' && $dateStart ? $dateStart->format('d/m/Y') :
                ($dateStart && $dateEnd ? $dateStart->format('d/m/Y') . ' → ' . $dateEnd->format('d/m/Y') : ''));

        $utilisateursText = $userFilter ?
                (strpos($userFilter, ',') !== false ?
                'Utilisateur(s) : ' . str_replace(',', ', ', $userFilter) :
                'Utilisateur : ' . $userFilter) : 'Tous les utilisateurs';

        $sheet->setCellValue('B1', 'Rapport Pointages');
        $sheet->mergeCells('B1:H1');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal('center');

        $sheet->setCellValue('B2', "Généré le {$dateGeneration} | Période : {$periodeText} | {$utilisateursText}");
        $sheet->mergeCells('B2:H2');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal('center');

        // ESPACE après en-tête
        $row = 4;

        //TABLEAU 1 : POINTAGES (B4-H)
        $sheet->setCellValue("B{$row}", 'Tableau des pointages');
        $sheet->mergeCells("B{$row}:H{$row}");
        $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center');

        $headers1 = ['Utilisateur', 'Date', 'Entrée', 'Début pause', 'Fin pause', 'Sortie', 'Total'];
        $sheet->fromArray($headers1, null, "B" . ($row + 1));

        $row += 2;
        foreach ($pointages as $pointage) {
            $sheet->setCellValue("B{$row}", $pointage->getUtilisateur()?->getUsername() ?? 'Inconnu');
            $sheet->setCellValue("C{$row}", $pointage->getDatePointage()?->format('d/m/Y') ?? '');
            $sheet->setCellValue("D{$row}", $pointage->getHeureEntree()?->format('H:i') ?? '');
            $sheet->setCellValue("E{$row}", $pointage->getHeureDebutPause()?->format('H:i') ?? '');
            $sheet->setCellValue("F{$row}", $pointage->getHeureFinPause()?->format('H:i') ?? '');
            $sheet->setCellValue("G{$row}", $pointage->getHeureSortie()?->format('H:i') ?? '');
            $sheet->setCellValue("H{$row}", $pointage->getTotalTravailFormatted() ?? '');
            $row++;
        }

        $finPointages = $row - 1;

        // ESPACE 3 lignes
        $row += 3;
        $recapTitre = $row;
        $recapHeader = $row + 1;

        //TABLEAU 2 : RÉCAPITULATIF (B-G)
        $sheet->setCellValue("B{$recapTitre}", 'Tableau récapitulatif');
        $sheet->mergeCells("B{$recapTitre}:E{$recapTitre}");
        $sheet->getStyle("B{$recapTitre}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("B{$recapTitre}")->getAlignment()->setHorizontal('center');

        $headers2 = ['Utilisateur', 'Période', 'Jours travaillés', 'Total'];
        $sheet->fromArray($headers2, null, "B{$recapHeader}");

        $row = $recapHeader + 1;
        $finRecap = $row;
        foreach ($recapParUtilisateur as $rowData) {
            $username = $rowData['user']?->getUsername() ?? $rowData['username'] ?? 'Inconnu';
            $periodeText = $period === 'global' ? 'Toute la période' :
                    ($period === 'day' && $dateStart ? $dateStart->format('d/m/Y') :
                    ($dateStart && $dateEnd ? 'du ' . $dateStart->format('d/m/Y') . ' au ' . $dateEnd->format('d/m/Y') : ''));

            $sheet->setCellValue("B{$row}", $username);
            $sheet->setCellValue("C{$row}", $periodeText);
            $sheet->setCellValue("D{$row}", $rowData['joursTravailles'] ?? 0);
            $sheet->setCellValue("E{$row}", $rowData['totalFormatted'] ?? '');
            $row++;
            $finRecap = $row - 1;
        }

        // MISE EN FORME
        // En-tête RAPPORT
        $sheet->getStyle('B1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8E8E8');

        // Headers POINTAGES
        $sheet->getStyle('B5:H5')->getFont()->setBold(true); // Ligne 5 = headers pointages
        $sheet->getStyle('B5:H5')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F2F2F2');

        //Headers RÉCAP
        $sheet->getStyle("B{$recapHeader}:E{$recapHeader}")->getFont()->setBold(true);
        $sheet->getStyle("B{$recapHeader}:E{$recapHeader}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F2F2F2');

        //BORDURES COMPLÈTES (titres + headers + données)
        $styleBordures = [
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '999999']
                ]
            ]
        ];
        $sheet->getStyle("B4:H{$finPointages}")->applyFromArray($styleBordures);
        $sheet->getStyle("B{$recapTitre}:E{$finRecap}")->applyFromArray($styleBordures);

        // Alignement
        $sheet->getStyle('D5:H' . $finRecap)->getAlignment()->setHorizontal('center');

        // Auto-dimensionnement
        foreach (range('B', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(3);

        // SAUVEGARDE
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($tempFile);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'pointages_' . date('Y-m-d') . '.xlsx');

        register_shutdown_function(function () use ($tempFile) {
            if (file_exists($tempFile))
                unlink($tempFile);
        });

        return $response;
    }
}
