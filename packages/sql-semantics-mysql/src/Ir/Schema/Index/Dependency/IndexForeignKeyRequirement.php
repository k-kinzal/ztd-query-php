<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The original physical key alternatives that can serve one native foreign-key side.
 * @visibility public
 * @example Naming the supporting keys of a persistent table
 *     $schema = new \SqlSemantics\Platform\MySql\Ir\Identifier('app');
 *     $table = new \SqlSemantics\Platform\MySql\Ir\Identifier('accounts');
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName($schema, $table);
 *     $owner = new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity($name, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement($owner, [new \SqlSemantics\Platform\MySql\Ir\Identifier('PRIMARY')]))->keys[0]->value // => 'PRIMARY'
 */
final class IndexForeignKeyRequirement
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> Original names of supporting physical keys
     */
    public readonly array $keys;

    /**
     * Checks that the native key alternatives belong to one persistent table.
     * @param list<Identifier> $keys Distinct physical names under native index name comparison
     */
    public function __construct(public readonly TableIdentity $owner, array $keys)
    {
        Check::input($owner->kind === ExternalRelationKind::BaseTable, 'Foreign-key index support requires a persistent owning table.');
        $checked = Check::listOf($keys, Identifier::class, 'Foreign-key index support needs one or more physical keys.', 1);
        $case = new NativeIdentifierCase();
        $seen = [];
        foreach ($checked as $key) {
            Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $key->value) === 1, 'A supporting index name has 1 to 64 characters and no trailing space.');
            $name = $case->fold($key->value);
            Check::input(!isset($seen[$name]), 'Foreign-key index support cannot repeat a physical key.');
            $seen[$name] = true;
        }
        $this->keys = $checked;
    }
}
