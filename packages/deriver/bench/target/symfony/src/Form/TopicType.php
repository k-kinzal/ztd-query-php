<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{HiddenType, SubmitType, TextType, TextareaType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class TopicType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, ['constraints' => [new Assert\Length(min:5, max:200)]])->add('body', TextareaType::class, ['constraints' => [new Assert\Length(min:10, max:50000)]])->add('requestKey', HiddenType::class, ['constraints' => [new Assert\Length(min:8, max:128)]])->add('publish', SubmitType::class);
    }
}
