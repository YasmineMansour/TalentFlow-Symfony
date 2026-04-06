<?php

namespace App\Form;

use App\Entity\Entreprise;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntrepriseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'attr' => ['placeholder' => 'Ex: TechCorp', 'minlength' => 2, 'maxlength' => 255],
            ])
            ->add('secteur', TextType::class, [
                'label' => 'Secteur d\'activité',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Informatique, Finance...', 'minlength' => 2, 'maxlength' => 255],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 10 Rue de la Paix, Tunis', 'minlength' => 5, 'maxlength' => 255],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: contact@entreprise.com'],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: +216 71 000 000', 'maxlength' => 20, 'pattern' => '[\+]?[0-9\s\-]{8,20}'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Décrivez l\'entreprise...', 'minlength' => 10],
            ])
            ->add('logo', TextType::class, [
                'label' => 'URL du logo',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: https://exemple.com/logo.png'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entreprise::class,
        ]);
    }
}
