<?php

namespace App\Form;

use App\Entity\Entretien;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntretienType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('candidatureId', ChoiceType::class, [
                'label' => 'Candidature',
                'choices' => $options['candidatures_choices'],
                'placeholder' => '-- Sélectionner une candidature --',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('dateHeure', DateTimeType::class, [
                'label' => 'Date et heure',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => array_combine(Entretien::TYPES, Entretien::TYPES),
                'placeholder' => 'Choisir un type',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_combine(Entretien::STATUTS, Entretien::STATUTS),
                'placeholder' => 'Choisir un statut',
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Salle, bureau, adresse...',
                ],
            ])
            ->add('lien', TextType::class, [
                'label' => 'Lien',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://meet...',
                ],
            ])
            ->add('noteTechnique', IntegerType::class, [
                'label' => 'Note technique',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 20,
                    'placeholder' => '0 - 20',
                ],
            ])
            ->add('noteCommunication', IntegerType::class, [
                'label' => 'Note communication',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 20,
                    'placeholder' => '0 - 20',
                ],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Observations, remarques...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entretien::class,
            'candidatures_choices' => [],
        ]);
    }
}