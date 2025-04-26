<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Service für die Validierung von Datumsbereichsangaben in Formularen
 */
class DateRangeValidationService
{
    /**
     * Validiert Start- und Enddatum aus einem direkten Formular-Request
     * 
     * Extrahiert und validiert Datumsangaben aus einem HTTP-Request. Prüft, ob die Daten
     * vorhanden, im korrekten Format sind und das Startdatum vor dem Enddatum liegt.
     * 
     * @param Request $request Der HTTP-Request mit den Formulardaten
     * @param string $startDateField Feldname für das Startdatum im Formular
     * @param string $endDateField Feldname für das Enddatum im Formular
     * @param \DateTime|null $defaultStartDate Standard-Startdatum, falls keines angegeben wurde
     * @param \DateTime|null $defaultEndDate Standard-Enddatum, falls keines angegeben wurde
     * 
     * @return array[\DateTime, \DateTime, array] Gibt [Startdatum, Enddatum, Fehler-Array] zurück
     */
    public function validateDateRange(
        Request $request, 
        string $startDateField = 'startDate', 
        string $endDateField = 'endDate',
        ?\DateTime $defaultStartDate = null,
        ?\DateTime $defaultEndDate = null
    ): array {
        $startDate = $defaultStartDate ?? (new \DateTime())->modify('-30 days');
        $endDate = $defaultEndDate ?? new \DateTime();
        $errors = [];
        
        $startDateStr = $request->request->get($startDateField);
        $endDateStr = $request->request->get($endDateField);
        
        if (!$startDateStr) {
            $errors[] = 'Start date is required';
        } else {
            try {
                $startDate = new \DateTime($startDateStr);
            } catch (\Exception $e) {
                $errors[] = 'Invalid start date format';
            }
        }
        
        if (!$endDateStr) {
            $errors[] = 'End date is required';
        } else {
            try {
                $endDate = new \DateTime($endDateStr);
            } catch (\Exception $e) {
                $errors[] = 'Invalid end date format';
            }
        }
        
        if (isset($startDate) && isset($endDate) && $startDate > $endDate) {
            $errors[] = 'Start date must be before end date';
        }
        
        return [$startDate, $endDate, $errors];
    }

    /**
     * Berechnet die prozentuale Differenz zwischen zwei Werten
     * 
     * Bestimmt, um wie viel Prozent sich der zweite Wert vom ersten unterscheidet.
     * Ein positives Ergebnis bedeutet eine Zunahme, ein negatives eine Abnahme.
     * 
     * @param float|null $firstValue Basiswert für den Vergleich
     * @param float|null $secondValue Vergleichswert
     * @return float|null Prozentuale Differenz oder null bei ungültigen Eingaben
     */
    public function calculatePercentageDifference(?float $firstValue, ?float $secondValue): ?float
    {
        if ($firstValue === null || $firstValue <= 0 || $secondValue === null) {
            return null;
        }
        
        return (($secondValue - $firstValue) / $firstValue) * 100;
    }
}