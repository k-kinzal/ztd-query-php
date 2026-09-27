<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use InvalidArgumentException;

/**
 * A type the binder knows only by its declared name: a user-defined, domain, or unmodeled type.
 *
 * The name parts are decoded like identifiers. Modifiers written after such a
 * name are not interpreted; the typed declaration a column keeps still has them.
 *
 * @example Reading a qualified type name
 *     $type = new \SqlSemantics\Statement\Declaration\TypeName(['app', 'money_amount']);
 *     $type->qualifiedName() // => 'app.money_amount'
 *     $type->equals(new \SqlSemantics\Statement\Declaration\TypeName(['app', 'money_amount'])) // => true
 *
 * @visibility public
 */
final class TypeName
{
    /**
     * @param list<string> $parts Qualified name in declaration order, decoded like identifiers; never empty
     * @throws InvalidArgumentException When the name is not a non-empty ordered list of strings
     */
    public function __construct(public readonly array $parts)
    {
        Invariant::names($parts);
        Invariant::ensure($parts !== [], 'A named type needs at least one name part.');
    }

    /**
     * Compares two names part by part, without applying a dialect case policy.
     */
    public function equals(self $other): bool
    {
        return $this->parts === $other->parts;
    }

    /**
     * Joins the parts for diagnostics; the parts themselves stay decoded.
     */
    public function qualifiedName(): string
    {
        return implode('.', $this->parts);
    }
}
