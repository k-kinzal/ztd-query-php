<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Config\Fields;

final class Evidence
{
    public function __construct(public readonly string $selector, public readonly string $quote)
    {
    }

    public static function from(mixed $value): self
    {
        $data = Fields::mapping($value, 'evidence');
        Fields::keys($data, ['selector', 'quote'], 'evidence');
        return new self(Fields::text($data, 'selector'), Fields::text($data, 'quote'));
    }
}
