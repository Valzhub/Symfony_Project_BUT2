<?php

namespace App\Form;

// RegistrationFormType.php — Définit le formulaire d'inscription avec tous les champs personnels et un mot de passe optionnel.


use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', null, [
                'label' => 'Adresse email',
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir une adresse email'),
                    new Email(
                        message: 'L\'adresse email "{{ value }}" n\'est pas valide.',
                        mode: 'strict' // force le format x@y.z
                    ),
                ],
            ])
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
            ->add('plainPassword', PasswordType::class, [
                'mapped'   => false,
                'required' => false,
                'attr'     => [
                    'autocomplete' => 'new-password',
                    'placeholder'  => 'Optionnel',
                ],
                'label'       => 'Mot de passe',
                'constraints' => [
                    new Length([
                        'min'        => 6,
                        'minMessage' => 'Le mot de passe doit faire au moins {{ limit }} caractères',
                        'max'        => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}