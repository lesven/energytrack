<?php

namespace App\Form;

use App\DTO\SingleMeterReadingDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SingleMeterReadingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // We only need the value input here. The meter association is handled
        // in the collection rendering in the template or the main form type.
        $builder
            ->add('value', NumberType::class, [
                'label' => false, // Label will be the meter name in the main template
                'required' => false, // Allow submitting without entering a value for every meter
                'html5' => true, // Use HTML5 number input
                'attr' => [
                    'step' => 'any' // Allow decimals
                ]
            ]);
        
        // We don't add the 'meter' field here as it's pre-populated in the controller
        // and shouldn't be changed by the user in this context.
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SingleMeterReadingDTO::class,
        ]);
    }
}
