<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Entreprise;
use App\Entity\Offre;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OffreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'offre',
                'attr' => ['placeholder' => 'Ex: Développeur PHP Senior', 'minlength' => 3, 'maxlength' => 255],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'Décrivez le poste...', 'minlength' => 10],
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Tunis, Sfax...', 'minlength' => 2, 'maxlength' => 255],
            ])
            ->add('entreprise', EntityType::class, [
                'class' => Entreprise::class,
                'choice_label' => 'nom',
                'label' => 'Entreprise',
                'required' => false,
                'placeholder' => '-- Sélectionner une entreprise --',
            ])
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => '-- Sélectionner une catégorie --',
            ])
            ->add('typeContrat', ChoiceType::class, [
                'label' => 'Type de contrat',
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Stage' => 'Stage',
                    'Freelance' => 'Freelance',
                    'Alternance' => 'Alternance',
                ],
            ])
            ->add('modeTravail', ChoiceType::class, [
                'label' => 'Mode de travail',
                'choices' => [
                    'Sur site' => 'ON_SITE',
                    'Télétravail' => 'REMOTE',
                    'Hybride' => 'HYBRID',
                ],
            ])
            ->add('salaireMin', NumberType::class, [
                'label' => 'Salaire minimum (DT)',
                'required' => false,
                'attr' => ['placeholder' => '0', 'min' => 0],
            ])
            ->add('salaireMax', NumberType::class, [
                'label' => 'Salaire maximum (DT)',
                'required' => false,
                'attr' => ['placeholder' => '0', 'min' => 0],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Offre active',
                'required' => false,
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Publiée' => 'PUBLISHED',
                    'Brouillon' => 'DRAFT',
                    'Fermée' => 'CLOSED',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offre::class,
        ]);
    }
}
