<?php

declare(strict_types=1);

namespace Deriver\Model\Expansion;

use Closure;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;

/**
 * An expression presented to a rule before any of its inputs are expanded.
 * @example Demanding one operand
 *     $request = new \Deriver\Model\Expansion\Request('binary', '+', new \Deriver\Reference\SourceRef('s', 'a.php', 0, 3), static fn ($key) => \Deriver\Value\Term::constant($key));
 *     $request->input(1)->native() // => 1
 * @visibility public
 */
final class Request
{
    /**
     * @param Closure(int|string): Term $expand
     */
    public function __construct(public readonly string $operation, public readonly string $name, public readonly SourceRef $source, private readonly Closure $expand)
    {
    }

    /**
     * Demands one operand or argument; other inputs remain unopened.
     */
    public function input(int|string $key): Term
    {
        return ($this->expand)($key);
    }
}
