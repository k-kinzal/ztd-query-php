<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Input\Fields;

final class TestReference
{
    public function __construct(public readonly string $runner, public readonly string $target)
    {
    }

    public static function from(mixed $value): self
    {
        $data = Fields::mapping($value, 'test');
        Fields::keys($data, ['runner', 'target'], 'test');
        return new self(Fields::text($data, 'runner'), Fields::text($data, 'target'));
    }
}
