<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

/**
 * A rowid table's integer identity, optionally shared with an INTEGER PRIMARY KEY declaration.
 * It is distinct from the explicit columns used by a star projection or an implicit INSERT list.
 * @visibility public
 * @example Resolving a built-in row identifier spelling
 *     (new \SqlSemantics\Statement\Schema\SqliteRowIdentifier())->matches('_rowid_', \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive) // => true
 */
final class SqliteRowIdentifier
{
    /**
     * The actual column identity shared by every unshadowed rowid spelling.
     */
    public readonly Column $column;

    /**
     * An alias must be the original non-NULL integer primary-key declaration.
     */
    public function __construct(public readonly ?Column $alias = null)
    {
        assert($alias === null || ($alias->type->name === Builtin::Integer && $alias->nullability === Nullability::NotNull), 'A rowid alias has a non-NULL integer declaration.');
        $this->column = $alias ?? new Column(new Name('rowid'), new TypeDescriptor(Builtin::Integer, affinity: Affinity::Integer), Nullability::NotNull);
    }

    /**
     * Declared columns take precedence; the containing table decides whether a spelling is shadowed.
     */
    public function matches(string $name, Comparison $comparison): bool
    {
        return $comparison->equal($name, 'rowid') || $comparison->equal($name, '_rowid_') || $comparison->equal($name, 'oid');
    }
}
