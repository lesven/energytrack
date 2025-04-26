<?php

namespace App\Form;

use App\DTO\BatchReadingDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BatchReadingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('readingDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Reading Date',
                'data' => new \DateTime(), // Default to today
                'required' => true,
            ])
            ->add('readings', CollectionType::class, [
                'entry_type' => SingleMeterReadingType::class,
                'label' => false, // Labels handled in the template iteration
                'allow_add' => false, // We pre-populate all meters
                'allow_delete' => false,
                // 'by_reference' => false is important when dealing with collections of objects
                // to ensure setters are called on the parent object (BatchReadingDTO)
                'by_reference' => false, 
            ])
             ->add('save', SubmitType::class, [
                'label' => 'Save Readings',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BatchReadingDTO::class,
        ]);
    }
}
