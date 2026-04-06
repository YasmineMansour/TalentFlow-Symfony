<?php

namespace App\Form;

use App\Entity\DecisionFinale;
use App\Entity\Entretien;
use App\Repository\EntretienRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DecisionFinaleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentEntretienId = $options['current_entretien_id'];

        $builder
            ->add('entretien', EntityType::class, [
                'class' => Entretien::class,
                'label' => 'Entretien',
                'choice_label' => static fn (Entretien $entretien): string => $entretien->getDisplayLabel(),
                'placeholder' => 'Choisir un entretien réalisé',
                'query_builder' => static fn (EntretienRepository $repository) => $repository->createAvailableForDecisionQueryBuilder($currentEntretienId),
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('decision', ChoiceType::class, [
                'label' => 'Décision',
                'choices' => array_combine(DecisionFinale::DECISIONS, DecisionFinale::DECISIONS),
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Explication de la décision...',
                    'maxlength' => 255,
                ],
            ])
            ->add('score', NumberType::class, [
                'label' => 'Score',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'step' => 0.01,
                    'min' => 0,
                    'max' => 20,
                    'placeholder' => 'Score final',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DecisionFinale::class,
            'current_entretien_id' => null,
        ]);
    }
}