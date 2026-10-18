<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EditPost
{
    public function __construct(
        #[Assert\Length(min:2, max:50000)] public string $body,
        #[Assert\Length(min:3, max:500)] public string $reason,
        #[Assert\Positive] public int $version,
    ) {
    }
}
