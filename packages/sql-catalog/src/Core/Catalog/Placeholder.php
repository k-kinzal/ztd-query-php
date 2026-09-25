<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

/**
 * One bind parameter of a catalogued statement, with what it is bound to.
 *
 * @visibility root
 */
final class Placeholder
{
    /**
     * @param string $token The parameter exactly as written in the statement
     * @param int $position The zero-based order of the parameter in the statement
     * @param string|null $name The parameter name, for parameters bound by name
     * @param ValueDomain|null $value What the parameter is bound to, when a binding was found
     */
    public function __construct(
        public readonly string $token,
        public readonly int $position,
        public readonly ?string $name,
        public readonly ?ValueDomain $value,
    ) {
    }

    /**
     * The key the parameter is reported under.
     */
    public function key(): string
    {
        return $this->name ?? (string) $this->position;
    }

    /**
     * The same parameter, bound to the given value.
     */
    public function withValue(?ValueDomain $value): self
    {
        return new self($this->token, $this->position, $this->name, $value);
    }
}
