<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A resolved relation column that owns a native sequence.
 *
 * @visibility public
 * @example Quoting a sequence dependency
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner(new $name('app'), new $name('accounts'), new $name('id')))->quoted() // => '"app"."accounts"."id"'
 */
final class SequenceOwner
{
    use Snapshot;

    /**
     * Fixes the exact owner coordinate and its native role.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $relation,
        public readonly Identifier $column,
        public readonly SequenceOwnerKind $kind = SequenceOwnerKind::BaseTable,
    ) {
        Check::input(!IndexSchemas::sessionInternal($schema->value), 'A sequence owner cannot use a numbered temporary namespace.');
    }

    /**
     * Emits the declared field coordinate without reference to the analysis context.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->relation->quoted() . '.' . $this->column->quoted();
    }
}
