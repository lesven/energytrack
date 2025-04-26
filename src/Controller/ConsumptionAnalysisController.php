<?php

namespace App\Controller;

use App\Entity\Meter;
use App\Form\ConsumptionComparisonType;
use App\Form\CustomDateRangeType;
use App\Repository\MeterRepository;
use App\Service\AnalysisService;
use App\Service\DateRangeValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Controller für Funktionen zur Verbrauchsanalyse
 * 
 * Bietet verschiedene Methoden zur Analyse und Visualisierung von
 * Verbrauchsdaten aus Zählerablesungen über bestimmte Zeiträume.
 */
#[Route('/analysis')]
final class ConsumptionAnalysisController extends AbstractController
{
    /**
     * Konstruktor mit Dependency Injection für benötigte Services
     * 
     * @param AnalysisService $analysisService Service für Verbrauchsberechnungen und -analysen
     * @param DateRangeValidationService $validationService Service für Validierung von Datumsangaben
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF-Token-Manager für direkte Formulare
     */
    public function __construct(
        private readonly AnalysisService $analysisService,
        private readonly DateRangeValidationService $validationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    /**
     * Haupteinstiegspunkt für die Verbrauchsanalyse
     * 
     * Zeigt eine Übersicht aller verfügbaren Zähler an, für die eine Analyse durchgeführt werden kann.
     * 
     * @param MeterRepository $meterRepository Repository für Zugriff auf Zähler-Entitäten
     * @return Response Twig-Template mit der Liste der verfügbaren Zähler
     */
    #[Route('', name: 'app_consumption_analysis_index', methods: ['GET'])]
    public function index(MeterRepository $meterRepository): Response
    {
        return $this->render('consumption_analysis/index.html.twig', [
            'meters' => $meterRepository->findAll(),
        ]);
    }

    /**
     * Zeigt Verbrauchsmuster nach Jahr, Monat, Quartal und Woche
     * 
     * Analysiert die Verbrauchsdaten eines Zählers und stellt sie nach verschiedenen
     * Zeitperioden gruppiert dar.
     * 
     * @param Meter $meter Der zu analysierende Zähler
     * @return Response Twig-Template mit den aufbereiteten Verbrauchsdaten
     */
    #[Route('/average/{id}', name: 'app_consumption_analysis_average', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function average(Meter $meter): Response
    {
        $yearlyData = $this->analysisService->calculateYearlySubPeriodTotals($meter);

        return $this->render('consumption_analysis/average.html.twig', [
            'meter' => $meter,
            'yearlyData' => $yearlyData,
        ]);
    }

    /**
     * Ermöglicht Analyse für einen benutzerdefinierten Datumsbereich
     * 
     * Stellt ein Formular bereit, mit dem der Benutzer einen Zeitraum auswählen kann,
     * und berechnet den Gesamtverbrauch für diesen Zeitraum.
     * 
     * @param Request $request Der HTTP-Request mit den Formulardaten
     * @param Meter $meter Der zu analysierende Zähler
     * @return Response Twig-Template mit Formular und ggf. Analyseergebnissen
     */
    #[Route('/custom/{id}', name: 'app_consumption_analysis_custom', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function custom(Request $request, Meter $meter): Response
    {
        $form = $this->createForm(CustomDateRangeType::class);
        $form->handleRequest($request);

        $totalConsumption = null;
        $readingsInRange = [];
        $startDate = null;
        $endDate = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $startDate = $data['startDate'];
            $endDate = $data['endDate'];

            $readingsInRange = $this->analysisService->getReadingsInRange($meter, $startDate, $endDate);
            $totalConsumption = $this->analysisService->calculateTotalConsumption($readingsInRange);
        }

        return $this->render('consumption_analysis/custom.html.twig', [
            'meter' => $meter,
            'form' => $form,
            'totalConsumption' => $totalConsumption,
            'readingsInRange' => $readingsInRange,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'formSubmitted' => $form->isSubmitted(),
        ]);
    }

    /**
     * Vergleicht den Verbrauch zwischen zwei Zeiträumen
     * 
     * Stellt ein Formular bereit, mit dem der Benutzer zwei Zeiträume auswählen kann,
     * und berechnet den Verbrauch für beide Zeiträume sowie die prozentuale Veränderung.
     * 
     * @param Request $request Der HTTP-Request mit den Formulardaten
     * @param Meter $meter Der zu analysierende Zähler
     * @return Response Twig-Template mit Formular und ggf. Vergleichsergebnissen
     */
    #[Route('/compare/{id}', name: 'app_consumption_analysis_compare', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function compare(Request $request, Meter $meter): Response
    {
        $form = $this->createForm(ConsumptionComparisonType::class);
        $form->handleRequest($request);

        $firstPeriodConsumption = null;
        $secondPeriodConsumption = null;
        $percentageDifference = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Calculate first period consumption
            $firstPeriodReadings = $this->analysisService->getReadingsInRange(
                $meter, 
                $data['firstPeriodStart'], 
                $data['firstPeriodEnd']
            );
            $firstPeriodConsumption = $this->analysisService->calculateTotalConsumption($firstPeriodReadings);

            // Calculate second period consumption
            $secondPeriodReadings = $this->analysisService->getReadingsInRange(
                $meter, 
                $data['secondPeriodStart'], 
                $data['secondPeriodEnd']
            );
            $secondPeriodConsumption = $this->analysisService->calculateTotalConsumption($secondPeriodReadings);

            $percentageDifference = $this->validationService->calculatePercentageDifference(
                $firstPeriodConsumption, 
                $secondPeriodConsumption
            );
        }

        return $this->render('consumption_analysis/compare.html.twig', [
            'meter' => $meter,
            'form' => $form,
            'firstPeriodConsumption' => $firstPeriodConsumption,
            'secondPeriodConsumption' => $secondPeriodConsumption,
            'percentageDifference' => $percentageDifference,
            'formSubmitted' => $form->isSubmitted(),
        ]);
    }

    /**
     * Alternative Implementierung für benutzerdefinierte Datumsbereichsanalyse mit direktem Formular
     * 
     * Verwendet ein manuell erstelltes Formular mit direkter CSRF-Token-Validierung anstelle
     * des Symfony Form-Systems. Zeigt auch, wie manuelle Formularvalidierung funktioniert.
     * 
     * @param Request $request Der HTTP-Request mit den Formulardaten
     * @param Meter $meter Der zu analysierende Zähler
     * @return Response Twig-Template mit Formular und ggf. Analyseergebnissen
     */
    #[Route('/direct/{id}', name: 'app_consumption_analysis_direct', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function direct(Request $request, Meter $meter): Response
    {
        $startDate = (new \DateTime())->modify('-30 days');
        $endDate = new \DateTime();
        $totalConsumption = null;
        $readingsInRange = [];
        $isSubmitted = false;
        $errors = [];
        
        if ($request->isMethod('POST')) {
            $isSubmitted = true;
            
            // Validate CSRF token
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('direct_form', $submittedToken)) {
                $errors[] = 'Invalid CSRF token';
            } else {
                try {
                    // Process form data using the validation service
                    [$startDate, $endDate, $validationErrors] = $this->validationService->validateDateRange($request);
                    $errors = array_merge($errors, $validationErrors);
                    
                    if (empty($errors)) {
                        $readingsInRange = $this->analysisService->getReadingsInRange($meter, $startDate, $endDate);
                        $totalConsumption = $this->analysisService->calculateTotalConsumption($readingsInRange);
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Error processing form: ' . $e->getMessage();
                }
            }
        }
        
        return $this->render('consumption_analysis/direct.html.twig', [
            'meter' => $meter,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'csrfToken' => $this->csrfTokenManager->getToken('direct_form')->getValue(),
            'totalConsumption' => $totalConsumption,
            'readingsInRange' => $readingsInRange,
            'isSubmitted' => $isSubmitted,
            'errors' => $errors
        ]);
    }
}