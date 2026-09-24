<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator\Qualified;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL operator named by its symbol and an optional schema path, as written in OPERATOR(schema.symbol) or as a bare symbol the package does not classify.
 * No operator catalog is consulted: the reference stays unresolved, so an operation through it has no derived result type.
 * @visibility public
 * @example Reading a schema-qualified operator
 *     $operator = new \SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator(['geo'], '<->');
 *     [$operator->qualifier, $operator->symbol, $operator->spelling()] // => [['geo'], '<->', 'OPERATOR(geo.<->)']
 * @example Rejecting a name that is not an operator symbol
 *     new \SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator(['geo'], 'distance'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class QualifiedOperator
{
    /**
     * @var list<string> The schema, or the database and the schema, before the symbol; empty when unqualified
     */
    public readonly array $qualifier;

    /**
     * An operator symbol is up to 63 operator characters without a comment start; the qualifier has at most two nonempty names.
     * @param list<string> $qualifier
     * @throws InvalidStructure
     */
    public function __construct(array $qualifier, public readonly string $symbol)
    {
        Collections::strings($qualifier);
        if (count($qualifier) > 2 || in_array('', $qualifier, true)) {
            throw new InvalidStructure('An operator qualifier names at most a database and a schema, each nonempty.');
        }
        if (strlen($symbol) > 63 || preg_match('/^[-+*\/<>=~!@#%^&|`?]+$/', $symbol) !== 1 || str_contains($symbol, '--') || str_contains($symbol, '/*')) {
            throw new InvalidStructure('An operator symbol consists of up to 63 operator characters and cannot start a comment.');
        }
        $this->qualifier = $qualifier;
    }

    /**
     * Returns OPERATOR(path.symbol) with unquoted names, the spelling used for diagnostics and expression labels.
     */
    public function spelling(): string
    {
        return 'OPERATOR(' . implode('.', [...$this->qualifier, $this->symbol]) . ')';
    }
}
