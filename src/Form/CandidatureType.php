<?php

namespace App\Form;

use App\Entity\Candidature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titrePoste', TextType::class, [
                'label' => 'Titre du poste',
                'attr' => ['placeholder' => 'Ex: Développeur PHP', 'class' => 'form-control'],
            ])
            ->add('entreprise', TextType::class, [
                'label' => 'Entreprise',
                'attr' => ['placeholder' => 'Nom de l\'entreprise', 'class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du poste',
                'required' => false,
                'attr' => ['placeholder' => 'Décrivez le poste...', 'class' => 'form-control', 'rows' => 4],
            ])
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat',
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Stage' => 'Stage',
                    'Alternance' => 'Alternance',
                    'Freelance' => 'Freelance',
                ],
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'En attente',
                    'Acceptée' => 'Acceptée',
                    'Refusée' => 'Refusée',
                    'Entretien' => 'Entretien',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateCandidature', DateType::class, [
                'label' => 'Date de candidature',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateEntretien', DateType::class, [
                'label' => 'Date d\'entretien',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => ['placeholder' => 'Ville ou adresse', 'class' => 'form-control'],
            ])
            ->add('salaireSouhaite', MoneyType::class, [
                'label' => 'Salaire souhaité (TND)',
                'currency' => 'TND',
                'required' => false,
                'attr' => ['placeholder' => '0.00', 'class' => 'form-control'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['placeholder' => 'Notes personnelles...', 'class' => 'form-control', 'rows' => 3],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
        ]);
    }
}
