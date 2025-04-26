<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formular für die Auswahl eines benutzerdefinierten Datumsbereichs zur Verbrauchsanalyse
 */
class CustomDateRangeType extends AbstractType
{
    /**
     * Erstellt die Formularfelder für den Datumsbereich mit Start- und Enddatum sowie Submit-Button
     *
     * @param FormBuilderInterface $builder Symfony's Formular-Builder
     * @param array $options Optionen des Formulars, die über configureOptions festgelegt werden können
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
            ]);
    }

    /**
     * Konfiguriert die Standardoptionen des Formulars
     * 
     * @param OptionsResolver $resolver Der Symfony OptionsResolver für Formularoptionen
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'custom_date_form',
        ]);
    }
}