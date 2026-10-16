<?php

namespace App\Form;

// ReservationFormType.php — Définit le formulaire de réservation avec les dates de début et de fin.


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReservationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'widget'      => 'single_text',
                'label'       => 'Date de début',
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir une date de début'),
                    new GreaterThan(value: 'today', message: 'La date de début doit être dans le futur'),
                ],
            ])
            ->add('endDate', DateType::class, [
                'widget'      => 'single_text',
                'label'       => 'Date de fin',
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir une date de fin'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}