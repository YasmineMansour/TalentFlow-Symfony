<?php

namespace App\Form;

use App\Entity\Candidature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isCandidat = $options['is_candidat'] ?? false;

        $builder
            ->add('titrePoste', TextType::class, [
                'label' => 'Titre du poste',
                'attr' => ['placeholder' => 'Ex: Développeur PHP', 'class' => 'form-control', 'minlength' => 3, 'maxlength' => 150],
            ])
            ->add('entreprise', TextType::class, [
                'label' => 'Entreprise',
                'attr' => ['placeholder' => 'Nom de l\'entreprise', 'class' => 'form-control', 'minlength' => 2, 'maxlength' => 150],
                'disabled' => $isCandidat,
                'mapped' => !$isCandidat,
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
                'disabled' => $isCandidat,
                'mapped' => !$isCandidat,
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['placeholder' => '+216 XX XXX XXX', 'class' => 'form-control', 'maxlength' => 20],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email du candidat',
                'required' => true,
                'attr' => ['placeholder' => 'email@exemple.com', 'class' => 'form-control', 'maxlength' => 180],
            ])
            ->add('niveauEtudes', ChoiceType::class, [
                'label' => 'Niveau d\'études',
                'required' => false,
                'choices' => [
                    'Bac' => 'Bac',
                    'Bac+2' => 'Bac+2',
                    'Bac+3 (Licence)' => 'Bac+3',
                    'Bac+5 (Master/Ingénieur)' => 'Bac+5',
                    'Doctorat' => 'Doctorat',
                    'Autre' => 'Autre',
                ],
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('anneesExperience', IntegerType::class, [
                'label' => 'Années d\'expérience',
                'required' => false,
                'attr' => ['placeholder' => '0', 'class' => 'form-control', 'min' => 0, 'max' => 50],
            ])
            ->add('competences', TextareaType::class, [
                'label' => 'Compétences clés',
                'required' => false,
                'attr' => ['placeholder' => 'PHP, Symfony, JavaScript, SQL...', 'class' => 'form-control', 'rows' => 3, 'maxlength' => 1000],
            ])
            ->add('salaireSouhaite', MoneyType::class, [
                'label' => 'Salaire souhaité (TND)',
                'currency' => 'TND',
                'required' => false,
                'attr' => ['placeholder' => '0.00', 'class' => 'form-control', 'min' => 0, 'max' => 999999.99],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu / Ville',
                'required' => false,
                'attr' => ['placeholder' => 'Votre ville ou adresse', 'class' => 'form-control', 'maxlength' => 100],
            ])
            ->add('dateEntretienSouhaitee', DateType::class, [
                'label' => 'Date d\'entretien souhaitée',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('cvFile', FileType::class, [
                'label' => 'CV (PDF, DOC, DOCX)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier PDF, DOC ou DOCX.',
                    ])
                ],
                'attr' => ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx'],
            ])
            ->add('lettreMotivationFile', FileType::class, [
                'label' => 'Lettre de motivation (PDF, DOC, DOCX)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier PDF, DOC ou DOCX.',
                    ])
                ],
                'attr' => ['class' => 'form-control', 'accept' => '.pdf,.doc,.docx'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Lettre de présentation / Message',
                'required' => false,
                'attr' => ['placeholder' => 'Présentez-vous et expliquez votre motivation...', 'class' => 'form-control', 'rows' => 5, 'maxlength' => 3000],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes personnelles',
                'required' => false,
                'attr' => ['placeholder' => 'Notes, remarques, disponibilités...', 'class' => 'form-control', 'rows' => 3, 'maxlength' => 2000],
            ])
        ;

        // Champs admin/RH seulement
        if (!$isCandidat) {
            $builder
                ->add('statut', ChoiceType::class, [
                    'label' => 'Statut',
                    'choices' => [
                        'En attente' => 'En attente',
                        'Validée RH' => 'Validée RH',
                        'Entretien' => 'Entretien',
                        'Acceptée' => 'Acceptée',
                        'Refusée' => 'Refusée',
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
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
            'is_candidat' => false,
        ]);
    }
}
