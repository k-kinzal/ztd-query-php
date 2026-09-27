<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use InvalidArgumentException;
use SqlSemantics\Statement\Declaration\TableDefinition;

/**
 * A table name written in a statement and what it resolves to.
 *
 * The value is the name as written, so it can be found and rewritten in the
 * statement by identity. A grammar that writes a qualified name as separate
 * values of one form, as SQLite's FROM writes a schema and a table, has no
 * single value for it: the values are all of them, in writing order, and
 * the value is the first. The name parts are decoded, in writing order, the
 * schema before the table when the name is qualified.
 *
 * @visibility public
 * @example Reading a resolved reference
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $users = $semantics->analyze('CREATE TABLE users (id INTEGER)', []);
 *     $reference = $semantics->analyze('SELECT id FROM users', [$users])->resolution?->references[0];
 *     [$reference?->name, $reference?->kind?->name, $reference?->declaration === $users] // => [['users'], 'Dependency', true]
 */
final class Reference
{
    /**
     * Every value that writes the name, in writing order; the value alone for a name written as one value.
     *
     * @var non-empty-list<Element>
     */
    public readonly array $values;

    /**
     * @param Element $value The name as written in the statement
     * @param non-empty-list<string> $name The decoded name parts, in writing order
     * @param ReferenceKind $kind What the name resolves to
     * @param Statement|null $declaration The dependency that declares the table, when one does
     * @param TableDefinition|null $table The declared table, when the name resolves to one
     * @param bool $conditional Whether the statement only declares or drops the table if that is possible
     * @param list<Element> $values Every value that writes the name, in writing order, the value first; empty when the value alone writes it
     *
     * @throws InvalidArgumentException When the values are not a list of values starting with the value, or an undeclared table or a common table expression has a declaration
     */
    public function __construct(
        public readonly Element $value,
        public readonly array $name,
        public readonly ReferenceKind $kind,
        public readonly ?Statement $declaration = null,
        public readonly ?TableDefinition $table = null,
        public readonly bool $conditional = false,
        array $values = [],
    ) {
        Declaration\Invariant::names($name);
        Declaration\Invariant::ensure(!in_array($kind, [ReferenceKind::Undeclared, ReferenceKind::CommonTableExpression], true) || ($declaration === null && $table === null), 'A reference to an undeclared table or a common table expression has no declaration.');
        Declaration\Invariant::members($values, Element::class);
        Declaration\Invariant::ensure($values === [] || $values[0] === $value, 'The values that write a name start with the value of the reference.');
        $this->values = $values === [] ? [$value] : $values;
    }
}
