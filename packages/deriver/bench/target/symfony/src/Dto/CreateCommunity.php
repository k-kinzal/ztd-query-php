<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCommunity
{
    public function __construct(
        #[Assert\Length(min:3, max:80), Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D')] public string $slug,
        #[Assert\Length(min:3, max:120)] public string $name,
        #[Assert\Length(min:10, max:5000)] public string $description,
    ) {
    }
}
