<?php

namespace App\Form;

// VehicleType.php — Définit le formulaire de création et modification d'un véhicule (admin).


use App\Entity\Feature;
use App\Entity\TypeVehicle;
use App\Entity\Vehicle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class VehicleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom',
            ])
            ->add('capacity', null, [
                'label' => 'Capacité',
            ])
            ->add('price', null, [
                'label' => 'Prix',
            ])
//            ->add('imagePath', null, [
//                'label' => 'Chemin de l\'image',
//            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du véhicule',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png'],
                        mimeTypesMessage: 'Veuillez télécharger une image valide (JPG ou PNG).'
                    )
                ],
            ])
            ->add('typeVehicle', EntityType::class, [
                'class' => TypeVehicle::class,
                'choice_label' => 'name',
            ])
            ->add('features', EntityType::class, [
                'class' => Feature::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vehicle::class,
        ]);
    }
}
