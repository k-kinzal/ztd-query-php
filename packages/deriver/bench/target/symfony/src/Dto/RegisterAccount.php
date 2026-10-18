<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterAccount
{
    public function __construct(
        #[Assert\Email, Assert\Length(max:254), Assert\NotBlank] public string $email,
        #[Assert\Length(min:2, max:80)] public string $name,
        #[Assert\Length(min:12, max:200)] public string $password,
    ) {
    }
}
