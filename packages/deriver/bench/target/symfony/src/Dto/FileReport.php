<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FileReport
{
    public function __construct(
        #[Assert\Choice(['spam','harassment','privacy','copyright','other'])] public string $category,
        #[Assert\Length(min:10, max:2000)] public string $reason,
    ) {
    }
}
