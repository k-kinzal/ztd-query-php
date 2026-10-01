<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Value\Term;

/**
 * A declared application entry and its explicitly supplied initial inputs.
 *
 * @visibility public
 * @example Declaring an entry
 *     (new \Deriver\Project\EntryPoint('App\\run'))->symbol // => 'App\\run'
 */
final class EntryPoint
{
    /**
     * @param string $symbol Fully qualified callable or script identity
     * @param array<int|string, Term> $arguments Positional or named initial values
     * @param Term|null $receiver Initial receiver identity, if supplied
     */
    public function __construct(
        public readonly string $symbol,
        public readonly array $arguments = [],
        public readonly ?Term $receiver = null,
    ) {
    }
}
