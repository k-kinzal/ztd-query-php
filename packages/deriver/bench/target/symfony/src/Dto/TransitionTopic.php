<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TransitionTopic
{
    public function __construct(
        #[Assert\Choice(['approve','lock','unlock','hide','restore','archive'])] public string $action,
        #[Assert\Positive] public int $version,
    ) {
    }
}
