<?php

declare(strict_types=1);

namespace Deriver\Model\Expansion;

use Closure;
use Deriver\Exception\InvalidInputException;
use Deriver\Value\Term;

/**
 * An explicit, versioned syntax or resolved-function replacement.
 * Return null to decline; return a residual Term for an unsupported selected case.
 * @example Replacing count without expanding its input
 *     $rule = \Deriver\Model\Expansion\Rule::constantFunction('count-one', '1', 'count', \Deriver\Value\Term::constant(1));
 *     $rule->name // => 'count'
 * @visibility public
 */
final class Rule
{
    /**
     * @param Closure(Request): ?Term $expand
     * @throws InvalidInputException If rule identity or source range is invalid
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $operation,
        public readonly string $name,
        public readonly Closure $expand,
        public readonly int $priority = 0,
        public readonly ?string $path = null,
        public readonly ?int $start = null,
        public readonly ?int $end = null,
    ) {
        if ($id === '' || $version === '' || $operation === '' || $start !== null && $start < 0 || $end !== null && $end < ($start ?? 0)) {
            throw new InvalidInputException('Expansion rules require an id, version and operation.');
        }
    }

    /**
     * Replaces an exactly resolved global function without demanding its arguments.
     */
    public static function constantFunction(string $id, string $version, string $function, Term $value): self
    {
        return new self($id, $version, 'function', strtolower(ltrim($function, '\\')), static fn (): Term => $value);
    }

    /**
     * Replaces one syntax operation without opening its children.
     */
    public static function constantExpression(string $id, string $version, string $operation, string $operator, Term $value): self
    {
        return new self($id, $version, $operation, $operator, static fn (): Term => $value);
    }

    /**
     * Checks the normalized operation, name and optional source range.
     */
    public function matches(Request $request): bool
    {
        $name = $request->operation === 'function' ? strtolower(ltrim($request->name, '\\')) : $request->name;
        return $request->operation === $this->operation && $name === $this->name && ($this->path === null || $this->path === $request->source->path) && ($this->start === null || $this->start === $request->source->start) && ($this->end === null || $this->end === $request->source->end);
    }
}
