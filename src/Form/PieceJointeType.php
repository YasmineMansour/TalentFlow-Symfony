<?php

namespace App\Form;

use App\Entity\PieceJointe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

class PieceJointeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeDocument', ChoiceType::class, [
                'label' => 'Type de document',
                'choices' => [
                    'CV' => 'CV',
                    'Lettre de motivation' => 'Lettre de motivation',
                    'Diplôme' => 'Diplôme',
                    'Certificat' => 'Certificat',
                    'Autre' => 'Autre',
                ],
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('fichierFile', VichFileType::class, [
                'label' => 'Fichier',
                'required' => true,
                'allow_delete' => false,
                'download_uri' => false,
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PieceJointe::class,
        ]);
    }
}
