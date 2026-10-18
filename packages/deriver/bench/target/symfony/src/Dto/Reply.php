<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Reply
{
    public function __construct(
        #[Assert\Length(min:2, max:50000)] public string $body,
    ) {
    }
}
