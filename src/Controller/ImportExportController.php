<?php

namespace App\Controller;

use App\Entity\Meter;
use App\Entity\MeterReading;
use App\Repository\MeterRepository;
use App\Repository\MeterReadingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\File;
use Psr\Log\LoggerInterface;
use \DateTime;
use \DateTimeImmutable;

#[Route('/import-export')]
class ImportExportController extends AbstractController
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_import_export')]
    public function index(Request $request, EntityManagerInterface $entityManager, MeterRepository $meterRepository): Response
    {
        // Setup CSV Import Form
        $importForm = $this->createFormBuilder()
            ->add('csvFile', FileType::class, [
                'label' => 'CSV-Datei',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                            'text/comma-separated-values',
                            'application/octet-stream', // Für allgemeine Binärdateien
                        ],
                        'mimeTypesMessage' => 'Bitte laden Sie eine gültige CSV-Datei hoch',
                    ])
                ],
            ])
            ->add('import', SubmitType::class, [
                'label' => 'Daten importieren',
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->getForm();
        
        $importForm->handleRequest($request);
        $importResult = null;
        $debugInfo = [];
        
        // Grundlegende Debug-Informationen immer sammeln
        $debugInfo['request_method'] = $request->getMethod();
        $debugInfo['form_submitted'] = $importForm->isSubmitted();
        
        if ($importForm->isSubmitted()) {
            $this->logger->info('Importformular wurde abgesendet');
            $debugInfo['form_valid'] = $importForm->isValid();
            
            if ($importForm->isValid()) {
                $this->logger->info('Importformular ist gültig');
                /** @var UploadedFile $csvFile */
                $csvFile = $importForm->get('csvFile')->getData();
                
                if ($csvFile) {
                    // Datei-Informationen für Debugging
                    $debugInfo['file'] = [
                        'name' => $csvFile->getClientOriginalName(),
                        'mime_type' => $csvFile->getMimeType(),
                        'size' => $csvFile->getSize(),
                    ];
                    
                    $this->logger->info('CSV-Datei hochgeladen', [
                        'name' => $csvFile->getClientOriginalName(),
                        'mime' => $csvFile->getMimeType(),
                        'size' => $csvFile->getSize()
                    ]);
                    
                    try {
                        // CSV-Inhalt zur Überprüfung in Debug-Informationen aufnehmen
                        $fileContent = file_get_contents($csvFile->getPathname());
                        $debugInfo['file_preview'] = substr($fileContent, 0, 500) . (strlen($fileContent) > 500 ? '...' : '');
                        
                        $importResult = $this->importCsv($csvFile, $entityManager, $meterRepository);
                        $this->logger->info('CSV-Import erfolgreich', ['count' => $importResult['count']]);
                        $this->addFlash('success', 'Import erfolgreich! ' . $importResult['count'] . ' Zählerstände wurden importiert.');
                        $debugInfo['import_result'] = $importResult;
                    } catch (\Exception $e) {
                        $errorMessage = $e->getMessage();
                        $this->logger->error('Fehler beim CSV-Import', [
                            'error' => $errorMessage,
                            'trace' => $e->getTraceAsString()
                        ]);
                        $this->addFlash('danger', 'Fehler beim Import: ' . $errorMessage);
                        $debugInfo['error'] = [
                            'message' => $errorMessage,
                            'trace' => $e->getTraceAsString()
                        ];
                    }
                } else {
                    $this->logger->warning('Keine Datei ausgewählt');
                    $this->addFlash('danger', 'Keine Datei ausgewählt.');
                    $debugInfo['error'] = 'Keine Datei ausgewählt';
                }
            } else {
                // Das Formular wurde abgesendet, ist aber nicht gültig
                $this->logger->warning('Formular ist ungültig');
                
                // Fehler aus dem Formular sammeln
                $errors = $importForm->getErrors(true);
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                
                $debugInfo['form_errors'] = $errorMessages;
                
                if (!empty($errorMessages)) {
                    $this->addFlash('danger', 'Das Formular enthält Fehler: ' . implode(', ', $errorMessages));
                } else {
                    $this->addFlash('danger', 'Das Formular enthält Fehler. Bitte überprüfen Sie das Dateiformat.');
                }
            }
        }

        // Sammle alle Flash-Nachrichten für Debug-Informationen
        $flashBag = $this->container->get('request_stack')->getSession()->getFlashBag();
        $allFlashes = [];
        foreach ($flashBag->peekAll() as $type => $messages) {
            $allFlashes[$type] = $messages;
        }
        $debugInfo['flashes'] = $allFlashes;

        return $this->render('import_export/index.html.twig', [
            'import_form' => $importForm,
            'import_result' => $importResult,
            'debug_info' => $debugInfo,
        ]);
    }

    #[Route('/export', name: 'app_export_csv')]
    public function exportCsv(MeterReadingRepository $readingRepository): Response
    {
        $readings = $readingRepository->findBy([], ['readingDate' => 'DESC']);
        
        $response = new StreamedResponse(function() use ($readings) {
            $handle = fopen('php://output', 'w+');
            
            // Write CSV header
            fputcsv($handle, ['meter_type', 'reading_value', 'reading_date']);
            
            // Write readings data
            foreach ($readings as $reading) {
                fputcsv($handle, [
                    $reading->getMeter()->getType(),
                    $reading->getValue(),
                    $reading->getReadingDate()->format('Y-m-d'),
                ]);
            }
            
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'meter_readings_export_' . date('Y-m-d') . '.csv'
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
    
    /**
     * Import readings from CSV file
     * 
     * @param UploadedFile $csvFile
     * @param EntityManagerInterface $entityManager
     * @param MeterRepository $meterRepository
     * 
     * @return array Import result statistics
     * 
     * @throws \Exception If import fails
     */
    private function importCsv(UploadedFile $csvFile, EntityManagerInterface $entityManager, MeterRepository $meterRepository): array
    {
        $this->logger->info('Starte CSV-Import', ['file' => $csvFile->getClientOriginalName()]);
        $filePath = $csvFile->getPathname();
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            $this->logger->error('Datei konnte nicht geöffnet werden', ['path' => $filePath]);
            throw new \Exception('Datei konnte nicht geöffnet werden.');
        }
        
        // Lese den ersten Teil des Dateiinhalts für Debug-Zwecke
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            $this->logger->error('Dateiinhalt konnte nicht gelesen werden');
            throw new \Exception('Dateiinhalt konnte nicht gelesen werden.');
        } else {
            $this->logger->info('Dateiinhalt gelesen', [
                'length' => strlen($fileContent), 
                'preview' => substr($fileContent, 0, 200)
            ]);
        }
        
        // Parse header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->logger->error('CSV-Header konnte nicht gelesen werden');
            throw new \Exception('CSV-Datei ist leer oder konnte nicht gelesen werden.');
        }

        // Remove BOM if present
        if (isset($header[0]) && str_starts_with($header[0], "\u{FEFF}")) {
            $header[0] = substr($header[0], 3);
        }
        
        $this->logger->info('CSV-Header gefunden', ['header' => implode(',', $header)]);
        
        // Normalisiere Header (Leerzeichen entfernen, Kleinbuchstaben)
        $normalizedHeader = array_map(function($item) {
            return strtolower(trim($item));
        }, $header);
        
        // Überprüfe den Header auf die erforderlichen Spalten
        $expectedHeader = ['meter_type', 'reading_value', 'reading_date'];
        $missingColumns = array_diff($expectedHeader, $normalizedHeader);
        $extraColumns = array_diff($normalizedHeader, $expectedHeader);
        
        if (!empty($missingColumns) || !empty($extraColumns)) {
            fclose($handle);
            $errorMsg = '';
            
            if (!empty($missingColumns)) {
                $errorMsg .= 'Fehlende Spalten: ' . implode(', ', $missingColumns) . '. ';
            }
            
            if (!empty($extraColumns)) {
                $errorMsg .= 'Unerwartete Spalten: ' . implode(', ', $extraColumns) . '. ';
            }
            
            $errorMsg .= 'Erwartetes Format: ' . implode(', ', $expectedHeader) . '. ';
            $errorMsg .= 'Gefunden: ' . implode(', ', $header);
            
            $this->logger->error('Ungültiger CSV-Header', [
                'expected' => implode(',', $expectedHeader),
                'found' => implode(',', $normalizedHeader),
                'missing' => $missingColumns,
                'extra' => $extraColumns
            ]);
            
            throw new \Exception('Ungültiges CSV-Format. ' . $errorMsg);
        }
        
        $entityManager->beginTransaction();
        $count = 0;
        $lineNumber = 1; // Start at 1 to account for header row
        
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                $this->logger->debug('Verarbeite Zeile', ['line' => $lineNumber, 'data' => implode(',', $row)]);
                
                // Überspringe leere Zeilen
                if (empty(array_filter($row))) {
                    $this->logger->debug('Überspringe leere Zeile', ['line' => $lineNumber]);
                    continue;
                }
                
                if (count($row) !== 3) {
                    $this->logger->error('Ungültige Spaltenanzahl', [
                        'line' => $lineNumber,
                        'expected' => 3,
                        'found' => count($row),
                        'data' => $row
                    ]);
                    throw new \Exception("Zeile $lineNumber: Ungültige Anzahl an Spalten. Erwartet werden 3 Spalten, gefunden: " . count($row));
                }
                
                [$meterType, $readingValue, $readingDate] = array_map('trim', $row);
                
                // Validate meter type
                $meterType = strtolower($meterType); // Normalisiere den Zählertyp
                if (!in_array($meterType, ['electricity', 'gas', 'water'])) {
                    $this->logger->error('Ungültiger Zählertyp', [
                        'line' => $lineNumber,
                        'type' => $meterType
                    ]);
                    throw new \Exception("Zeile $lineNumber: Ungültiger Zählertyp '$meterType'. Erlaubt sind 'electricity', 'gas', 'water'.");
                }
                
                // Validate reading value (ersetze Komma durch Punkt für deutsches Format)
                $readingValue = str_replace(',', '.', $readingValue);
                if (!is_numeric($readingValue) || floatval($readingValue) < 0) {
                    $this->logger->error('Ungültiger Zählerstand', [
                        'line' => $lineNumber,
                        'value' => $readingValue
                    ]);
                    throw new \Exception("Zeile $lineNumber: Ungültiger Zählerstand '$readingValue'. Muss eine positive Zahl sein.");
                }
                
                // Validate reading date
                $date = DateTime::createFromFormat('Y-m-d', $readingDate);
                if (!$date || $date->format('Y-m-d') !== $readingDate) {
                    $this->logger->error('Ungültiges Datum', [
                        'line' => $lineNumber,
                        'date' => $readingDate
                    ]);
                    throw new \Exception("Zeile $lineNumber: Ungültiges Datum '$readingDate'. Format muss YYYY-MM-DD sein.");
                }
                
                // Find or create meter
                $meter = $this->findOrCreateMeter($meterType, $entityManager, $meterRepository);
                
                // Create new reading
                $reading = new MeterReading();
                $reading->setMeter($meter);
                $reading->setValue(floatval($readingValue));
                $reading->setReadingDate($date);
                
                $entityManager->persist($reading);
                $this->logger->debug('Zählerstand gespeichert', [
                    'type' => $meterType,
                    'value' => floatval($readingValue),
                    'date' => $date->format('Y-m-d')
                ]);
                $count++;
            }
            
            if ($count === 0) {
                $this->logger->warning('Keine gültigen Datensätze gefunden');
                throw new \Exception('Keine gültigen Datensätze in der CSV-Datei gefunden.');
            }
            
            $this->logger->info('Speichere Änderungen in der Datenbank');
            $entityManager->flush();
            $entityManager->commit();
            fclose($handle);
            $this->logger->info('Import abgeschlossen', ['count' => $count]);
            
            return [
                'status' => 'success',
                'count' => $count,
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Fehler beim Import, führe Rollback durch', ['error' => $e->getMessage()]);
            $entityManager->rollback();
            
            if (is_resource($handle)) {
                fclose($handle);
            }
            
            throw $e;
        }
    }
    
    /**
     * Find an existing meter of the given type or create a new one
     */
    private function findOrCreateMeter(string $meterType, EntityManagerInterface $entityManager, MeterRepository $meterRepository): Meter
    {
        // Try to find existing meter of this type
        $meter = $meterRepository->findOneBy(['type' => $meterType]);
        
        if (!$meter) {
            // Create new meter if none exists
            $this->logger->info('Erstelle neuen Zähler', ['type' => $meterType]);
            $meter = new Meter();
            $meter->setName(ucfirst($meterType) . ' Meter');
            $meter->setType($meterType);
            $entityManager->persist($meter);
        }
        
        return $meter;
    }
    
    /**
     * Helper method to access the flash bag
     */
    private function getFlashBag()
    {
        return $this->container->get('request_stack')->getSession()->getFlashBag();
    }

    #[Route('/delete', name: 'app_import_export_delete', methods: ['POST'])]
    public function deleteByType(Request $request, MeterRepository $meterRepository, MeterReadingRepository $readingRepository): Response
    {
        $submittedToken = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('delete_meter_readings', $submittedToken)) {
            $this->addFlash('danger', 'Ungültiges CSRF-Token.');
            return $this->redirectToRoute('app_import_export');
        }

        $type = $request->request->get('meter_type');
        if (!in_array($type, ['electricity', 'gas', 'water'], true)) {
            $this->addFlash('danger', 'Ungültiger Zählertyp.');
            return $this->redirectToRoute('app_import_export');
        }

        $meters = $meterRepository->findBy(['type' => $type]);
        $deletedCount = 0;
        foreach ($meters as $meter) {
            $deleted = $readingRepository->createQueryBuilder('r')
                ->delete()
                ->where('r.meter = :meter')
                ->setParameter('meter', $meter)
                ->getQuery()
                ->execute();
            $deletedCount += $deleted;
        }

        $this->addFlash('success', sprintf('%d Zählerstände für %s gelöscht.', $deletedCount, ucfirst($type)));
        return $this->redirectToRoute('app_import_export');
    }
}
