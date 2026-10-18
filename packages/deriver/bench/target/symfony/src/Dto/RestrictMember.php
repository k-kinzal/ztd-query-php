<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RestrictMember
{
    public function __construct(
        #[Assert\Positive] public int $userId,
        #[Assert\Length(min:10, max:2000)] public string $reason,
        #[Assert\Range(min:1, max:2160)] public int $hours,
    ) {
    }
}
