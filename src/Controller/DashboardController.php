<?php

namespace App\Controller;

use App\Repository\MeterRepository;
use App\Repository\MeterReadingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(MeterRepository $meterRepository, MeterReadingRepository $meterReadingRepository): Response
    {
        // Get all meters
        $meters = $meterRepository->findAll();
        
        // Get recent readings (last 10)
        $recentReadings = $meterReadingRepository->findBy([], ['readingDate' => 'DESC'], 10);
        
        // Count by type
        $metersByType = [
            'electricity' => 0,
            'gas' => 0,
            'water' => 0,
            'other' => 0,
        ];
        
        foreach ($meters as $meter) {
            $type = $meter->getType();
            if (isset($metersByType[$type])) {
                $metersByType[$type]++;
            } else {
                $metersByType['other']++;
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'meters' => $meters,
            'recent_readings' => $recentReadings,
            'meters_by_type' => $metersByType,
            'total_meters' => count($meters),
            'total_readings' => $meterReadingRepository->count([]),
        ]);
    }
}
