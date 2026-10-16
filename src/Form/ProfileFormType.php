<?php

namespace App\Form;

// ProfileFormType.php — Définit le formulaire de modification des données personnelles (nom, prénom, adresse, téléphone, email).


use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'required' => false,
                'label'    => 'Prénom',
            ])
            ->add('lastName', TextType::class, [
                'required' => false,
                'label'    => 'Nom',
            ])
            ->add('address', TextareaType::class, [
                'required' => false,
                'label'    => 'Adresse',
            ])
            ->add('phone', TelType::class, [
                'required' => false,
                'label'    => 'Téléphone',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}