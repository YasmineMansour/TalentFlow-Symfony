<?php

namespace App\Form;

use App\Entity\Post;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'empty_data' => '',
                'attr' => ['placeholder' => 'Entrez le titre du post'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'empty_data' => '',
                'attr' => ['placeholder' => 'Entrez le contenu', 'rows' => 6],
            ])
            ->add('author', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn(User $u) => $u->getFullName(),
                'label' => 'Auteur',
                'placeholder' => 'Sélectionnez un auteur',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}
