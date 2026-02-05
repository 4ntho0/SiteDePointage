<?php

namespace App\Controller;

use App\Repository\PointageRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class AdminPointageExportController extends AbstractController
{
    private const FORMAT_HEURE_MINUTES = 'H:i';
    private const FORMAT_DATE_EXPORT_EXCEL = 'd/m/Y';
    private const FORMAT_DATE_PDF_FILENAME = 'Y-m-d';
    private const FORMAT_DATE_GENERATION = 'd/m/Y \\à H:i';
    private const CHAMP_DATE_POINTAGE = 'datePointage';

    private PointageRepository $repository;

    public function __construct(PointageRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/admin/pointages/export/pdf', name: 'admin.pointage.export.pdf')]
    public function exportPdf(Request $request): Response
    {
        $data = $this->prepareExportData($request);

        $html = $this->renderView('admin/_pointages_pdf.html.twig', [
            'pointages' => $data['pointages'],
            'recapParUtilisateur' => $data['recap'],
            'userFilter' => $data['userFilter'],
            'dateStart' => $data['dateStart'],
            'dateEnd' => $data['dateEnd'],
            'period' => $data['period']
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
            'Content-Disposition' => 'attachment; filename="pointages_' . date(self::FORMAT_DATE_PDF_FILENAME) . '.pdf"'
                ]
        );
    }

    #[Route('/admin/pointages/export/excel', name: 'admin.pointage.export.excel')]
    public function exportExcel(Request $request): Response
    {
        $data = $this->prepareExportData($request);

        $spreadsheet = $this->createSpreadsheet($data);
        $sheet = $spreadsheet->getActiveSheet();

        $lastPointageRow = $this->addPointagesTable($sheet, $data['pointages']);
        $this->addRecapTable(
            $sheet,
            $data['recap'],
            $data['period'],
            $data['dateStart'],
            $data['dateEnd'],
            $lastPointageRow
        );
        $this->applyStyling($sheet);

        return $this->generateExcelResponse($spreadsheet);
    }

    private function prepareExportData(Request $request): array
    {
        [$dateStart, $dateEnd, $period] = $this->getDateRangeFromRequest($request);

        return [
            'sortField' => $request->query->get('sort', self::CHAMP_DATE_POINTAGE),
            'sortOrder' => $request->query->get('order', 'DESC'),
            'userFilter' => $request->query->get('user'),
            'period' => $period,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'pointages' => $this->repository->findAllOrderByFieldWithLimit(
                $request->query->get('sort', self::CHAMP_DATE_POINTAGE),
                $request->query->get('order', 'DESC'),
                $request->query->get('user'),
                $dateStart,
                $dateEnd
            ),
            'recap' => $this->repository->getRecapParUtilisateur($dateStart, $dateEnd, $request->query->get('user'))
        ];
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
            'day' => [$today->modify('-' . ($page - 1) . ' days'), $today->modify('-' . ($page - 1) . ' days'), 'day'],
            'week' => $this->getWeekRange($today, $page),
            'month' => $this->getMonthRange($today, $page),
            'year' => $this->getYearRange($today, $page),
            default => [null, null, $period]
        };
    }

    private function createSpreadsheet(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pointages');
        $this->addHeader($sheet, $data);
        return $spreadsheet;
    }

    private function addHeader(Worksheet $sheet, array $data): void
    {
        $dateGeneration = (new \DateTime())->format(self::FORMAT_DATE_GENERATION);
        $periodeText = $this->formatPeriodeText($data['period'], $data['dateStart'], $data['dateEnd']);
        $utilisateursText = $this->formatUtilisateursText($data['userFilter']);

        $sheet->setCellValue('B1', 'Rapport Pointages');
        $sheet->mergeCells('B1:H1');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(22);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal('center');

        $sheet->setCellValue('B2', "Généré le {$dateGeneration} | Période : {$periodeText} | {$utilisateursText}");
        $sheet->mergeCells('B2:H2');
        $sheet->getStyle('B2')->getAlignment()->setHorizontal('center');
    }

    private function addPointagesTable(Worksheet $sheet, array $pointages): int
    {
        $row = 4;
        $sheet->setCellValue("B{$row}", 'Tableau des pointages');
        $sheet->mergeCells("B{$row}:H{$row}");
        $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center');
        $row++;

        $headers = ['Utilisateur', 'Date', 'Entrée', 'Début pause', 'Fin pause', 'Sortie', 'Total'];
        $sheet->fromArray($headers, null, "B{$row}");
        $sheet->getStyle("B{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("B{$row}:H{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F2F2F2');
        $row++;

        foreach ($pointages as $pointage) {
            $this->fillPointageRow($sheet, $row, $pointage);
            if (($row - 5) % 2 == 0) {
                $sheet->getStyle("B{$row}:H{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FAFAFA');
            }
            $row++;
        }

        return $row - 1;
    }

    private function fillPointageRow(Worksheet $sheet, int $row, $pointage): void
    {
        $sheet->setCellValue("B{$row}", $pointage->getUtilisateur()?->getUsername() ?? 'Inconnu');
        $sheet->setCellValue("C{$row}", $pointage->getDatePointage()?->format(self::FORMAT_DATE_EXPORT_EXCEL) ?? '');
        $sheet->setCellValue("D{$row}", $pointage->getHeureEntree()?->format(self::FORMAT_HEURE_MINUTES) ?? '');
        $sheet->setCellValue("E{$row}", $pointage->getHeureDebutPause()?->format(self::FORMAT_HEURE_MINUTES) ?? '');
        $sheet->setCellValue("F{$row}", $pointage->getHeureFinPause()?->format(self::FORMAT_HEURE_MINUTES) ?? '');
        $sheet->setCellValue("G{$row}", $pointage->getHeureSortie()?->format(self::FORMAT_HEURE_MINUTES) ?? '');
        $sheet->setCellValue("H{$row}", $pointage->getTotalTravailFormatted() ?? '');
    }

    private function addRecapTable(
        Worksheet $sheet,
        array $recap,
        string $period,
        ?\DateTimeInterface $dateStart,
        ?\DateTimeInterface $dateEnd,
        int $lastPointageRow
    ): void {
        $row = $lastPointageRow + 4;

        $sheet->setCellValue("B{$row}", 'Récapitulatif par utilisateur');
        $sheet->mergeCells("B{$row}:E{$row}");
        $sheet->getStyle("B{$row}")->getFont()->setSize(18)->setBold(true);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center');
        $row += 2;

        $headers = ['Utilisateur', 'Période', 'Jours travaillés', 'Total'];
        $sheet->fromArray($headers, null, "B{$row}");
        $sheet->getStyle("B{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("B{$row}:E{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F2F2F2');
        $row++;

        foreach ($recap as $rowData) {
            $sheet->setCellValue("B{$row}", $rowData['user']?->getUsername() ?? $rowData['username'] ?? 'Inconnu');
            $sheet->setCellValue("C{$row}", $this->formatRecapPeriode($period, $dateStart, $dateEnd));
            $sheet->setCellValue("D{$row}", $rowData['joursTravailles'] ?? 0);
            $sheet->setCellValue("E{$row}", $rowData['totalFormatted'] ?? '');
            $sheet->getStyle("E{$row}")->getFont()->setBold(true);
            $row++;
        }
    }

    private function applyStyling(Worksheet $sheet): void
    {
        $thinBorder = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ];
        $sheet->getStyle('B1:H60')->applyFromArray($thinBorder);
        $sheet->getStyle('B4:H60')->getAlignment()->setHorizontal('center');

        foreach (range('B', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(3);
    }

    private function generateExcelResponse(Spreadsheet $spreadsheet): Response
    {
        $tempFile = sys_get_temp_dir() . '/pointages_' . uniqid() . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $response = new BinaryFileResponse($tempFile);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set(
            'Content-Disposition',
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'pointages_' . date(self::FORMAT_DATE_PDF_FILENAME) . '.xlsx'
        );

        register_shutdown_function(function () use ($tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });

        return $response;
    }

    private function formatPeriodeText(
        string $period,
        ?\DateTimeInterface $dateStart,
        ?\DateTimeInterface $dateEnd
    ): string {
        return match (true) {
            $period === 'global' => 'Toute la période',
            $period === 'day' && $dateStart => $dateStart->format(self::FORMAT_DATE_EXPORT_EXCEL),
            $dateStart && $dateEnd =>
            $dateStart->format(self::FORMAT_DATE_EXPORT_EXCEL) . ' → ' . $dateEnd->
                    format(self::FORMAT_DATE_EXPORT_EXCEL),
            default => $period
        };
    }

    private function formatUtilisateursText(?string $userFilter): string
    {
        if (!$userFilter) {
            return 'Tous les utilisateurs';
        }
        if (strpos($userFilter, ',') !== false) {
            return 'Utilisateur(s) : ' . str_replace(',', ', ', $userFilter);
        }
        return 'Utilisateur : ' . $userFilter;
    }

    private function formatRecapPeriode(
        string $period,
        ?\DateTimeInterface $dateStart,
        ?\DateTimeInterface $dateEnd
    ): string {
        return match (true) {
            $period === 'global' => 'Toute la période',
            $period === 'day' && $dateStart => $dateStart->format(self::FORMAT_DATE_EXPORT_EXCEL),
            $dateStart && $dateEnd => 'du ' . $dateStart->
                    format(self::FORMAT_DATE_EXPORT_EXCEL) . ' au ' . $dateEnd->
                    format(self::FORMAT_DATE_EXPORT_EXCEL),
            default => $period
        };
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
