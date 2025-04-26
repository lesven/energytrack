<?php

namespace App\Controller;

use App\Entity\Meter;
use App\Entity\MeterReading;
use App\Repository\MeterReadingRepository;
use App\Repository\MeterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\DTO\BatchReadingDTO;
use App\DTO\SingleMeterReadingDTO;
use App\Form\BatchReadingType;

#[Route('/meter/reading')]
final class MeterReadingController extends AbstractController
{
    #[Route('', name: 'app_meter_reading_index', methods: ['GET'])]
    public function index(MeterReadingRepository $meterReadingRepository): Response
    {
        return $this->render('meter_reading/index.html.twig', [
            'meter_readings' => $meterReadingRepository->findBy([], ['readingDate' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_meter_reading_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, MeterRepository $meterRepository): Response
    {
        // Check if there are any meters first
        $meters = $meterRepository->findAll();
        if (count($meters) === 0) {
            $this->addFlash('warning', 'Please create a meter first before adding readings.');
            return $this->redirectToRoute('app_meter_index');
        }

        $meterReading = new MeterReading();
        $meterReading->setReadingDate(new \DateTime());
        
        $form = $this->createFormBuilder($meterReading)
            ->add('meter', EntityType::class, [
                'class' => Meter::class,
                'choice_label' => function(Meter $meter) {
                    return $meter->getName() . ' (' . $meter->getType() . ')';
                },
            ])
            ->add('value', NumberType::class, [
                'label' => 'Reading Value',
                'scale' => 2,
            ])
            ->add('readingDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Reading Date',
                'data' => new \DateTime(),
            ])
            ->add('note', TextareaType::class, [
                'required' => false,
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($meterReading);
            $entityManager->flush();

            $this->addFlash('success', 'Reading added successfully!');
            return $this->redirectToRoute('app_meter_reading_index');
        }

        return $this->render('meter_reading/new.html.twig', [
            'meter_reading' => $meterReading,
            'form' => $form,
        ]);
    }

    #[Route('/details/{id}', name: 'app_meter_reading_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(MeterReading $meterReading): Response
    {
        return $this->render('meter_reading/show.html.twig', [
            'meter_reading' => $meterReading,
        ]);
    }

    #[Route('/edit/{id}', name: 'app_meter_reading_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, MeterReading $meterReading, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createFormBuilder($meterReading)
            ->add('meter', EntityType::class, [
                'class' => Meter::class,
                'choice_label' => function(Meter $meter) {
                    return $meter->getName() . ' (' . $meter->getType() . ')';
                },
            ])
            ->add('value', NumberType::class, [
                'label' => 'Reading Value',
                'scale' => 2,
            ])
            ->add('readingDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Reading Date',
            ])
            ->add('note', TextareaType::class, [
                'required' => false,
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $meterReading->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Reading updated successfully!');
            return $this->redirectToRoute('app_meter_reading_index');
        }

        return $this->render('meter_reading/edit.html.twig', [
            'meter_reading' => $meterReading,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}', name: 'app_meter_reading_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, MeterReading $meterReading, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$meterReading->getId(), $request->request->get('_token'))) {
            $entityManager->remove($meterReading);
            $entityManager->flush();
            $this->addFlash('success', 'Reading deleted successfully!');
        }

        return $this->redirectToRoute('app_meter_reading_index');
    }

    #[Route('/batch/new', name: 'app_meter_reading_batch_new', methods: ['GET', 'POST'])]
    public function batchNew(Request $request, MeterRepository $meterRepository, EntityManagerInterface $entityManager): Response
    {
        $batchReadingDTO = new BatchReadingDTO();
        $meters = $meterRepository->findAll(); // Or filter for active meters if needed

        // Pre-populate the DTO with an entry for each meter
        foreach ($meters as $meter) {
            $singleReadingDTO = new SingleMeterReadingDTO();
            $singleReadingDTO->meter = $meter; // Associate the meter
            $batchReadingDTO->readings[] = $singleReadingDTO;
        }

        $form = $this->createForm(BatchReadingType::class, $batchReadingDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $readingDate = $batchReadingDTO->readingDate;
            $readingsSaved = 0;

            foreach ($batchReadingDTO->readings as $singleReading) {
                // Only save if a value was actually entered
                if ($singleReading->value !== null) {
                    $meterReading = new MeterReading();
                    $meterReading->setMeter($singleReading->meter);
                    $meterReading->setValue($singleReading->value);
                    $meterReading->setReadingDate(clone $readingDate); // Clone DateTime to avoid issues if reused
                    $entityManager->persist($meterReading);
                    $readingsSaved++;
                }
            }

            if ($readingsSaved > 0) {
                $entityManager->flush();
                $this->addFlash('success', sprintf('%d meter readings saved successfully.', $readingsSaved));
            } else {
                 // Handle case where form is valid but no values were entered (if not caught by DTO validation)
                 $this->addFlash('warning', 'No meter readings were entered.');
            }


            // Redirect back to the batch form or to the index
            return $this->redirectToRoute('app_meter_reading_batch_new', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('meter_reading/batch_new.html.twig', [
            'form' => $form->createView(),
            // Pass meters separately if needed for display logic not handled by the form theme
            // 'meters' => $meters
        ]);
    }
}
