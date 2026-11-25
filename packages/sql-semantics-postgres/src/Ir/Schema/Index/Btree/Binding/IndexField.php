<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The canonical name and native type of an indexed physical slot.
 * @visibility public
 * @example Holding a Boolean partial-index operand
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('active');
 *     $kind = \SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind::Boolean;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexField($name, $kind))->kind->value // => 'bool'
 */
final class IndexField
{
    use Snapshot;

    /**
     * Fixes the field type in the checked B-tree operand profile.
     */
    public function __construct(public readonly Identifier $name, public readonly ColumnKind $kind)
    {
        Check::input(
            $kind === ColumnKind::Int4 || $kind === ColumnKind::Int8 || $kind === ColumnKind::Boolean,
            'A B-tree in this profile requires integer or Boolean physical column types.'
        );
    }
}
