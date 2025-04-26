<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formular für den Vergleich des Verbrauchs zwischen zwei Zeiträumen
 */
class ConsumptionComparisonType extends AbstractType
{
    /**
     * Erstellt die Formularfelder für zwei Zeiträume mit jeweils Start- und Enddatum sowie Submit-Button
     *
     * @param FormBuilderInterface $builder Symfony's Formular-Builder
     * @param array $options Optionen des Formulars, die über configureOptions festgelegt werden können
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstPeriodStart', DateType::class, [
                'widget' => 'single_text',
                'label' => 'First Period Start',
                'data' => (new \DateTime())->modify('-1 year'),
            ])
            ->add('firstPeriodEnd', DateType::class, [
                'widget' => 'single_text', 
                'label' => 'First Period End',
                'data' => (new \DateTime())->modify('-11 months'),
            ])
            ->add('secondPeriodStart', DateType::class, [
                'widget' => 'single_text', 
                'label' => 'Second Period Start',
                'data' => (new \DateTime())->modify('-1 month'),
            ])
            ->add('secondPeriodEnd', DateType::class, [
                'widget' => 'single_text', 
                'label' => 'Second Period End',
                'data' => new \DateTime(),
            ])
            ->add('compare', SubmitType::class, [
                'label' => 'Compare', 
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
            'csrf_token_id' => 'comparison_form',
        ]);
    }
}