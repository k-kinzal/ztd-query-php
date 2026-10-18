<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResolveReport
{
    public function __construct(
        #[Assert\Choice(['dismissed','upheld'])] public string $decision,
        #[Assert\Length(min:10, max:2000)] public string $reason,
    ) {
    }
}
