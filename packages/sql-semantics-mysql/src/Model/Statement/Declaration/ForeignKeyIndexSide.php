<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Declaration;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The original ordered fields of one retained foreign-key side on the altered table.
 * @visibility public
 * @example An existing parent side on the altered table
 *     $name = \SqlSemantics\Platform\MySql\Ir\Identifier::class;
 *     $table = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName(new $name('app'), new $name('accounts'));
 *     $owner = new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity($table, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable);
 *     $constraint = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity($owner, new $name('link'));
 *     (new \SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide($constraint, [new $name('account_id')], true))->fields[0]->value // => 'account_id'
 */
final class ForeignKeyIndexSide
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> The exact field order on this side
     */
    public readonly array $fields;

    /**
     * Checks the immutable side supplied by the original catalog.
     * @param list<Identifier> $fields Ordered full field identities
     */
    public function __construct(public readonly ForeignKeyConstraintIdentity $constraint, array $fields, public readonly bool $parent)
    {
        $checked = Check::listOf($fields, Identifier::class, 'An original foreign-key side needs checked fields.', 1);
        $case = new NativeIdentifierCase();
        $seen = [];
        foreach ($checked as $field) {
            $key = $case->fold($field->value);
            Check::input(!isset($seen[$key]), 'An original foreign-key side cannot repeat a field.');
            $seen[$key] = true;
        }
        $this->fields = $checked;
    }
}
