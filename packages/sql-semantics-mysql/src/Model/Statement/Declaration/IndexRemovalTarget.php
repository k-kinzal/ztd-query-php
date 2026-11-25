<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Declaration;

use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Construction input for removal of one physical index against its original declaration.
 * @visibility public
 * @example Describing a key that does not support a generating field
 *     $name = \SqlSemantics\Platform\MySql\Ir\Identifier::class;
 *     $table = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName(new $name('app'), new $name('accounts'));
 *     $owner = new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity($table, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable);
 *     $key = new \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity($owner, new $name('by_id'), \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole::Ordinary);
 *     (new \SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget($key, null))->index->name->value // => 'by_id'
 */
final class IndexRemovalTarget
{
    use Snapshot;

    /**
     * Checks the original role and names of any alternate support for this physical key.
     */
    public function __construct(
        public readonly IndexIdentity $index,
        public readonly ?IndexAutoIncrement $autoIncrement,
    ) {
        $case = new NativeIdentifierCase();
        $target = $case->fold($index->name->value);
        foreach ($autoIncrement->alternates ?? [] as $alternate) {
            Check::input($case->fold($alternate->value) !== $target, 'An AUTO_INCREMENT backing alternate must be a different physical key.');
        }
    }
}
