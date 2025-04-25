<?php

namespace App\Controller;

use App\Entity\Meter;
use App\Repository\MeterReadingRepository;
use App\Repository\MeterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/analysis')]
final class ConsumptionAnalysisController extends AbstractController
{
    private $csrfTokenManager;
    
    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }
    
    #[Route('', name: 'app_consumption_analysis_index', methods: ['GET'])]
    public function index(MeterRepository $meterRepository): Response
    {
        return $this->render('consumption_analysis/index.html.twig', [
            'meters' => $meterRepository->findAll(),
        ]);
    }

    #[Route('/average/{id}', name: 'app_consumption_analysis_average', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function average(Meter $meter, MeterReadingRepository $meterReadingRepository): Response
    {
        // Get readings for this meter, ordered by date
        $readings = $meterReadingRepository->findBy(['meter' => $meter], ['readingDate' => 'ASC']);
        
        // Calculate weekly, monthly and quarterly averages
        $weeklyAverage = $this->calculateAverageConsumption($readings, 'P7D');
        $monthlyAverage = $this->calculateAverageConsumption($readings, 'P1M');
        $quarterlyAverage = $this->calculateAverageConsumption($readings, 'P3M');
        
        return $this->render('consumption_analysis/average.html.twig', [
            'meter' => $meter,
            'weeklyAverage' => $weeklyAverage,
            'monthlyAverage' => $monthlyAverage,
            'quarterlyAverage' => $quarterlyAverage,
        ]);
    }
    
    #[Route('/custom/{id}', name: 'app_consumption_analysis_custom', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function custom(Request $request, Meter $meter, MeterReadingRepository $meterReadingRepository): Response
    {
        // Create a form with CSRF token
        $form = $this->createFormBuilder()
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Start Date',
                'data' => (new \DateTime())->modify('-30 days'),
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'End Date',
                'data' => new \DateTime(),
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Calculate',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();
            
        $form->handleRequest($request);
        
        $totalConsumption = null;
        $readingsInRange = [];
        $debugInfo = [
            'formSubmitted' => $form->isSubmitted(),
            'formValid' => $form->isSubmitted() ? $form->isValid() : null,
            'method' => $request->getMethod(),
        ];
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $startDate = $data['startDate'];
            $endDate = $data['endDate'];
            
            $debugInfo['startDate'] = $startDate->format('Y-m-d');
            $debugInfo['endDate'] = $endDate->format('Y-m-d');
            
            // Get readings in the selected date range
            $readingsInRange = $meterReadingRepository->createQueryBuilder('mr')
                ->where('mr.meter = :meter')
                ->andWhere('mr.readingDate >= :startDate')
                ->andWhere('mr.readingDate <= :endDate')
                ->setParameter('meter', $meter)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->orderBy('mr.readingDate', 'ASC')
                ->getQuery()
                ->getResult();
            
            $debugInfo['readingsCount'] = count($readingsInRange);
            
            // Calculate total consumption
            if (count($readingsInRange) >= 2) {
                $firstReading = reset($readingsInRange);
                $lastReading = end($readingsInRange);
                
                $totalConsumption = $lastReading->getValue() - $firstReading->getValue();
                $totalConsumption = max(0, $totalConsumption);
                
                $debugInfo['firstReadingValue'] = $firstReading->getValue();
                $debugInfo['lastReadingValue'] = $lastReading->getValue();
                $debugInfo['totalConsumption'] = $totalConsumption;
            }
        }
        
        return $this->render('consumption_analysis/custom.html.twig', [
            'meter' => $meter,
            'form' => $form,
            'totalConsumption' => $totalConsumption,
            'readingsInRange' => $readingsInRange,
            'formSubmitted' => $form->isSubmitted(),
            'debugInfo' => $debugInfo
        ]);
    }
    
    #[Route('/compare/{id}', name: 'app_consumption_analysis_compare', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function compare(Request $request, Meter $meter, MeterReadingRepository $meterReadingRepository): Response
    {
        // Create a proper form with CSRF protection
        $defaultData = [
            'firstPeriodStart' => (new \DateTime())->modify('-1 year'),
            'firstPeriodEnd' => (new \DateTime())->modify('-11 months'),
            'secondPeriodStart' => (new \DateTime())->modify('-1 month'),
            'secondPeriodEnd' => new \DateTime(),
        ];
        
        $form = $this->createFormBuilder($defaultData)
            ->add('firstPeriodStart', DateType::class, [
                'widget' => 'single_text',
                'label' => 'First Period Start',
            ])
            ->add('firstPeriodEnd', DateType::class, [
                'widget' => 'single_text',
                'label' => 'First Period End',
            ])
            ->add('secondPeriodStart', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Second Period Start',
            ])
            ->add('secondPeriodEnd', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Second Period End',
            ])
            ->add('compare', SubmitType::class, [
                'label' => 'Compare',
                'attr' => ['class' => 'btn-primary'],
            ])
            ->getForm();
            
        $form->handleRequest($request);
        
        $firstPeriodConsumption = null;
        $secondPeriodConsumption = null;
        $percentageDifference = null;
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $firstPeriodStart = $data['firstPeriodStart'];
            $firstPeriodEnd = $data['firstPeriodEnd'];
            $secondPeriodStart = $data['secondPeriodStart'];
            $secondPeriodEnd = $data['secondPeriodEnd'];
            
            // Get readings for first period
            $firstPeriodReadings = $meterReadingRepository->createQueryBuilder('mr')
                ->where('mr.meter = :meter')
                ->andWhere('mr.readingDate >= :startDate')
                ->andWhere('mr.readingDate <= :endDate')
                ->setParameter('meter', $meter)
                ->setParameter('startDate', $firstPeriodStart)
                ->setParameter('endDate', $firstPeriodEnd)
                ->orderBy('mr.readingDate', 'ASC')
                ->getQuery()
                ->getResult();
            
            // Get readings for second period
            $secondPeriodReadings = $meterReadingRepository->createQueryBuilder('mr')
                ->where('mr.meter = :meter')
                ->andWhere('mr.readingDate >= :startDate')
                ->andWhere('mr.readingDate <= :endDate')
                ->setParameter('meter', $meter)
                ->setParameter('startDate', $secondPeriodStart)
                ->setParameter('endDate', $secondPeriodEnd)
                ->orderBy('mr.readingDate', 'ASC')
                ->getQuery()
                ->getResult();
                
            // Calculate consumption for first period
            if (count($firstPeriodReadings) >= 2) {
                $firstReading = reset($firstPeriodReadings);
                $lastReading = end($firstPeriodReadings);
                
                $firstPeriodConsumption = $lastReading->getValue() - $firstReading->getValue();
                $firstPeriodConsumption = max(0, $firstPeriodConsumption);
            }
            
            // Calculate consumption for second period
            if (count($secondPeriodReadings) >= 2) {
                $firstReading = reset($secondPeriodReadings);
                $lastReading = end($secondPeriodReadings);
                
                $secondPeriodConsumption = $lastReading->getValue() - $firstReading->getValue();
                $secondPeriodConsumption = max(0, $secondPeriodConsumption);
            }
            
            // Calculate percentage difference
            if ($firstPeriodConsumption > 0 && $secondPeriodConsumption !== null) {
                $percentageDifference = (($secondPeriodConsumption - $firstPeriodConsumption) / $firstPeriodConsumption) * 100;
            }
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
    
    #[Route('/test/{id}', name: 'app_consumption_analysis_test', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function test(Request $request, Meter $meter): Response
    {
        $result = null;
        
        if ($request->isMethod('POST')) {
            $testValue = $request->request->get('testValue', 'No value received');
            $result = "Form submitted successfully. Received value: " . $testValue;
        }
        
        return $this->render('consumption_analysis/test.html.twig', [
            'meter' => $meter,
            'result' => $result
        ]);
    }
    
    #[Route('/basic', name: 'app_consumption_analysis_basic')]
    public function basic(Request $request): Response
    {
        $result = '';
        $submittedValue = '';
        $method = $request->getMethod();
        
        // Check if the form has been submitted
        if ($request->isMethod('POST')) {
            $submittedValue = $request->request->get('basic_input', 'no value');
            $result = "Form submitted successfully via $method. Received: $submittedValue";
        } else {
            $result = "No submission detected. Current method: $method";
        }
        
        // Return direct HTML response for simple debugging
        return new Response(
            '<html><body>
                <h1>Basic Form Test</h1>
                <p>' . $result . '</p>
                <form method="post">
                    <input type="text" name="basic_input" value="test value">
                    <button type="submit">Submit Basic Form</button>
                </form>
            </body></html>'
        );
    }
    
    #[Route('/direct/{id}', name: 'app_consumption_analysis_direct', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function direct(Request $request, Meter $meter, MeterReadingRepository $meterReadingRepository): Response
    {
        $tokenId = 'submit';
        $csrfToken = $this->csrfTokenManager->getToken($tokenId)->getValue();
        
        $startDate = (new \DateTime())->modify('-30 days');
        $endDate = new \DateTime();
        $totalConsumption = null;
        $readingsInRange = [];
        $isSubmitted = false;
        $errors = [];
        
        // Process form submission
        if ($request->isMethod('POST')) {
            $isSubmitted = true;
            
            // For Turbo-driven requests, CSRF tokens are sent as cookies, not form fields
            // Check for the token in both the request body and headers
            $submittedToken = $request->request->get('_csrf_token');
            if (!$submittedToken) {
                // Check for the header format that Turbo uses
                foreach ($request->headers->all() as $header => $value) {
                    if (strpos($header, 'submit') === 0) {
                        $submittedToken = $value[0];
                        break;
                    }
                }
            }

            // Get form data, ensuring we get it from the correct source depending on if it's a Turbo request
            try {
                $startDateStr = $request->request->get('startDate');
                $endDateStr = $request->request->get('endDate');
                
                if (!$startDateStr) {
                    $errors[] = 'Start date is required';
                } else {
                    $startDate = new \DateTime($startDateStr);
                }
                
                if (!$endDateStr) {
                    $errors[] = 'End date is required';
                } else {
                    $endDate = new \DateTime($endDateStr);
                }
                
                if (empty($errors)) {
                    // Get readings in the selected date range
                    $readingsInRange = $meterReadingRepository->createQueryBuilder('mr')
                        ->where('mr.meter = :meter')
                        ->andWhere('mr.readingDate >= :startDate')
                        ->andWhere('mr.readingDate <= :endDate')
                        ->setParameter('meter', $meter)
                        ->setParameter('startDate', $startDate)
                        ->setParameter('endDate', $endDate)
                        ->orderBy('mr.readingDate', 'ASC')
                        ->getQuery()
                        ->getResult();
                    
                    // Calculate total consumption
                    if (count($readingsInRange) >= 2) {
                        $firstReading = reset($readingsInRange);
                        $lastReading = end($readingsInRange);
                        
                        $totalConsumption = $lastReading->getValue() - $firstReading->getValue();
                        $totalConsumption = max(0, $totalConsumption);
                    }
                }
            } catch (\Exception $e) {
                $errors[] = 'Error processing form: ' . $e->getMessage();
            }
        }
        
        $debugInfo = [
            'submitted' => $isSubmitted,
            'errors' => $errors,
            'method' => $request->getMethod(),
            'request_format' => $request->getRequestFormat(),
            'headers' => array_keys($request->headers->all()),
            'request_body' => $request->request->all(),
            'cookies' => array_keys($request->cookies->all()),
            'readingsCount' => count($readingsInRange),
            'totalConsumption' => $totalConsumption,
        ];
        
        return $this->render('consumption_analysis/direct.html.twig', [
            'meter' => $meter,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'csrfToken' => $csrfToken,
            'totalConsumption' => $totalConsumption,
            'readingsInRange' => $readingsInRange,
            'isSubmitted' => $isSubmitted,
            'errors' => $errors,
            'debugInfo' => $debugInfo
        ]);
    }
    
    /**
     * Calculate average consumption for a given period
     * 
     * @param array $readings Array of MeterReading objects
     * @param string $interval DateInterval specification (e.g., 'P7D' for 7 days)
     * @return float|null Average consumption per period or null if not enough readings
     */
    private function calculateAverageConsumption(array $readings, string $interval): ?float
    {
        if (count($readings) < 2) {
            return null;
        }
        
        // Get first and last reading
        $firstReading = reset($readings);
        $lastReading = end($readings);
        
        // Calculate total consumption
        $totalConsumption = $lastReading->getValue() - $firstReading->getValue();
        if ($totalConsumption <= 0) {
            return null;
        }
        
        // Calculate total days between readings
        $daysDiff = $firstReading->getReadingDate()->diff($lastReading->getReadingDate())->days;
        if ($daysDiff <= 0) {
            return null;
        }
        
        // Calculate average consumption per day
        $averagePerDay = $totalConsumption / $daysDiff;
        
        // Convert to the requested interval
        $intervalObj = new \DateInterval($interval);
        $days = $intervalObj->d;
        
        // Add days from months and years if present
        if (isset($intervalObj->m)) {
            $days += $intervalObj->m * 30; // Approximate
        }
        if (isset($intervalObj->y)) {
            $days += $intervalObj->y * 365; // Approximate
        }
        
        return $averagePerDay * $days;
    }
    
    /**
     * Calculate the total consumption between a set of readings
     * 
     * @param array $readings Array of MeterReading objects
     * @return float|null Total consumption or null if not enough readings
     */
    private function calculateTotalConsumption(array $readings): ?float
    {
        if (count($readings) < 2) {
            return null;
        }
        
        // Get first and last reading
        $firstReading = reset($readings);
        $lastReading = end($readings);
        
        // Calculate total consumption
        $totalConsumption = $lastReading->getValue() - $firstReading->getValue();
        
        return max(0, $totalConsumption);
    }
}