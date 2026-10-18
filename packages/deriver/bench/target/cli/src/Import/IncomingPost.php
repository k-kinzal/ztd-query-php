<?php

declare(strict_types=1);

namespace HarborOps\Import;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class IncomingPost
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 128)] public string $id,
        #[Assert\NotBlank, Assert\Length(max: 128)] public string $memberId,
        #[Assert\NotBlank, Assert\Email, Assert\Length(max: 254)] public string $email,
        #[Assert\Length(min: 2, max: 80)] public string $displayName,
        #[Assert\Length(min: 5, max: 200)] public string $title,
        #[Assert\Length(min: 2, max: 50000)] public string $body,
        #[Assert\DateTime(format: DateTimeInterface::ATOM)] public string $publishedAt,
    ) {
    }
}
