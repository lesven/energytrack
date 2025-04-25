<?php

namespace App\Service;

use App\Entity\Meter;
use App\Entity\MeterReading;
use App\Repository\MeterReadingRepository;
use DateInterval;
use DateTimeInterface;

class AnalysisService
{
    public function __construct(private readonly MeterReadingRepository $meterReadingRepository)
    {
    }

    /**
     * Get readings for a meter within a specific date range, ordered by date.
     *
     * @param Meter $meter
     * @param DateTimeInterface $startDate
     * @param DateTimeInterface $endDate
     * @return MeterReading[]
     */
    public function getReadingsInRange(Meter $meter, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        return $this->meterReadingRepository->createQueryBuilder('mr')
            ->where('mr.meter = :meter')
            ->andWhere('mr.readingDate >= :startDate')
            ->andWhere('mr.readingDate <= :endDate')
            ->setParameter('meter', $meter)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('mr.readingDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate the total consumption between a set of readings.
     *
     * @param MeterReading[] $readings Array of MeterReading objects, ordered by date
     * @return float|null Total consumption or null if not enough readings
     */
    public function calculateTotalConsumption(array $readings): ?float
    {
        if (count($readings) < 2) {
            return null;
        }

        // Ensure readings are sorted by date (caller should ideally provide sorted data)
        usort($readings, fn($a, $b) => $a->getReadingDate() <=> $b->getReadingDate());

        $firstReading = reset($readings);
        $lastReading = end($readings);

        // Calculate total consumption
        $totalConsumption = $lastReading->getValue() - $firstReading->getValue();

        return max(0, $totalConsumption);
    }

    /**
     * Calculate the average daily consumption based on the first and last reading.
     *
     * @param MeterReading[] $readings Array of MeterReading objects
     * @return float|null Average daily consumption or null if not enough readings or time difference
     */
    public function calculateAverageDailyConsumption(array $readings): ?float
    {
        if (count($readings) < 2) {
            return null;
        }

        // Ensure readings are sorted by date
        usort($readings, fn($a, $b) => $a->getReadingDate() <=> $b->getReadingDate());

        $firstReading = reset($readings);
        $lastReading = end($readings);

        // Calculate total consumption
        $totalConsumption = $lastReading->getValue() - $firstReading->getValue();
        if ($totalConsumption < 0) {
            // Negative consumption is usually invalid
            return null;
        }

        // Calculate total seconds between readings for higher precision
        $secondsDiff = $lastReading->getReadingDate()->getTimestamp() - $firstReading->getReadingDate()->getTimestamp();

        if ($secondsDiff <= 0) {
            // Avoid division by zero or negative time difference
            // If consumption is also 0, the rate is 0. Otherwise, it's undefined/infinite.
            return $totalConsumption === 0.0 ? 0.0 : null;
        }

        // Calculate average consumption per second
        $averagePerSecond = $totalConsumption / $secondsDiff;

        // Convert average per second to average per day (86400 seconds in a day)
        $averagePerDay = $averagePerSecond * 86400;

        return $averagePerDay;
    }

    /**
     * Groups readings by year and computes total consumption per month, quarter, and ISO week for each year.
     * Note: The name 'average' in the route might be misleading as this calculates totals per sub-period.
     *
     * @param Meter $meter
     * @return array Structure: [year => ['monthly' => [...], 'quarterly' => [...], 'weeks' => [...]]]
     */
    public function calculateYearlySubPeriodTotals(Meter $meter): array
    {
        $readings = $this->meterReadingRepository->findBy(['meter' => $meter], ['readingDate' => 'ASC']);
        $yearlyData = [];
        $years = array_unique(array_map(fn($r) => $r->getReadingDate()->format('Y'), $readings));
        sort($years);

        foreach ($years as $year) {
            $yearReadings = array_filter($readings, fn($r) => $r->getReadingDate()->format('Y') === $year);
            
            // Sort readings within the year
             usort($yearReadings, fn($a, $b) => $a->getReadingDate() <=> $b->getReadingDate());

            // Monthly totals
            $monthly = [];
            foreach (range(1, 12) as $m) {
                $group = array_filter($yearReadings, fn($r) => (int)$r->getReadingDate()->format('n') === $m);
                $monthly[$m] = $this->calculateTotalConsumption($group);
            }

            // Quarterly totals
            $quarterly = [];
            foreach (range(1, 4) as $q) {
                $group = array_filter($yearReadings, fn($r) => ceil((int)$r->getReadingDate()->format('n') / 3) === $q);
                $quarterly[$q] = $this->calculateTotalConsumption($group);
            }

            // Weekly totals (ISO week)
            $weekly = [];
            $weekGroups = [];
            foreach ($yearReadings as $r) {
                // Use ISO-8601 week date (o-W) to handle year boundaries correctly
                $w = $r->getReadingDate()->format('o-W'); 
                $weekGroups[$w][] = $r;
            }
            ksort($weekGroups); // Sort by week string 'YYYY-WW'
            foreach ($weekGroups as $w => $group) {
                 // Pass the sorted group to calculation
                $weekly[$w] = $this->calculateTotalConsumption($group);
            }

            $yearlyData[$year] = ['monthly' => $monthly, 'quarterly' => $quarterly, 'weeks' => $weekly];
        }
        return $yearlyData;
    }
}
