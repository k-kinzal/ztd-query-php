<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublishTopic
{
    public function __construct(
        #[Assert\Length(min:5, max:200)] public string $title,
        #[Assert\Length(min:10, max:50000)] public string $body,
        #[Assert\Length(min:8, max:128)] public string $requestKey,
    ) {
    }
}
