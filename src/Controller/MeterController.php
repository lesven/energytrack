<?php

namespace App\Controller;

use App\Entity\Meter;
use App\Repository\MeterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/meter')]
final class MeterController extends AbstractController
{
    #[Route('/', name: 'app_meter_index', methods: ['GET'])]
    public function index(MeterRepository $meterRepository): Response
    {
        return $this->render('meter/index.html.twig', [
            'meters' => $meterRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_meter_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $meter = new Meter();
        $form = $this->createFormBuilder($meter)
            ->add('name')
            ->add('type', null, [
                'attr' => [
                    'placeholder' => 'electricity, gas, water'
                ]
            ])
            ->add('location')
            ->add('description')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($meter);
            $entityManager->flush();

            $this->addFlash('success', 'Meter created successfully!');
            return $this->redirectToRoute('app_meter_index');
        }

        return $this->render('meter/new.html.twig', [
            'meter' => $meter,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_meter_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Meter $meter): Response
    {
        return $this->render('meter/show.html.twig', [
            'meter' => $meter,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_meter_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Meter $meter, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createFormBuilder($meter)
            ->add('name')
            ->add('type', null, [
                'attr' => [
                    'placeholder' => 'electricity, gas, water'
                ]
            ])
            ->add('location')
            ->add('description')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $meter->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Meter updated successfully!');
            return $this->redirectToRoute('app_meter_index');
        }

        return $this->render('meter/edit.html.twig', [
            'meter' => $meter,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_meter_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Meter $meter, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$meter->getId(), $request->request->get('_token'))) {
            $entityManager->remove($meter);
            $entityManager->flush();
            $this->addFlash('success', 'Meter deleted successfully!');
        }

        return $this->redirectToRoute('app_meter_index');
    }
}
