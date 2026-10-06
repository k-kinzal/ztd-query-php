<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * A once-evaluated actual argument and its optional PHP reference address.
 *
 * @visibility root
 */
final class PassedArgument
{
    /**
     * @param Term $value value
     * @param string|null $name name
     * @param Location|null $location location
     * @param array<int|string, self> $elements Variadic arguments with original reference addresses
     * @param bool $writable Whether a by-reference binding may expose this address
     */
    public function __construct(
        public readonly Term $value,
        public readonly ?string $name = null,
        public readonly ?Location $location = null,
        public readonly array $elements = [],
        public readonly bool $writable = true,
    ) {
    }
}
