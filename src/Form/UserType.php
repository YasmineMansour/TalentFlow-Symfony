<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];
        $availableRoles = $options['available_roles'];

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Entrez le nom',
                    'class' => 'form-control',
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Entrez le prénom',
                    'class' => 'form-control',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'exemple@talentflow.com',
                    'class' => 'form-control',
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '+216XXXXXXXX ou 00216XXXXXXXX',
                    'class' => 'form-control',
                ],
            ])
        ;

        // Le champ rôle n'est affiché que si l'utilisateur a la permission de le modifier
        if ($availableRoles !== null) {
            $builder->add('roles', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => $availableRoles,
                'multiple' => true,
                'expanded' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ]);
        }

        $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => !$isEdit,
                'first_options' => [
                    'label' => $isEdit ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                    'constraints' => $isEdit ? [
                        new Length([
                            'min' => 12,
                            'max' => 4096,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        ]),
                        new Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/',
                            'message' => 'Le mot de passe doit contenir au moins 12 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.',
                        ]),
                    ] : [
                        new NotBlank([
                            'message' => 'Veuillez entrer un mot de passe.',
                        ]),
                        new Length([
                            'min' => 12,
                            'max' => 4096,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        ]),
                        new Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/',
                            'message' => 'Le mot de passe doit contenir au moins 12 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.',
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'available_roles' => [
                'Candidat' => 'ROLE_CANDIDAT',
                'Recruteur RH' => 'ROLE_RH',
                'Administrateur' => 'ROLE_ADMIN',
            ],
        ]);
    }
}
