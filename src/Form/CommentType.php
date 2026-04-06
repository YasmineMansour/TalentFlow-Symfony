<?php

namespace App\Form;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Commentaire',
                'empty_data' => '',
                'attr' => ['placeholder' => 'Écrivez votre commentaire', 'rows' => 4],
            ])
            ->add('post', EntityType::class, [
                'class' => Post::class,
                'choice_label' => 'title',
                'label' => 'Post',
                'placeholder' => 'Sélectionnez un post',
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
            'data_class' => Comment::class,
        ]);
    }
}
