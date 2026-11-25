<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A resolved external index owned by one persistent or session-local table.
 *
 * @visibility public
 * @example Retaining the physical primary key identity
 *     $identifier = \SqlSemantics\Platform\MySql\Ir\Identifier::class;
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName(new $identifier('app'), new $identifier('accounts'));
 *     $owner = new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity($name, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity($owner, new $identifier('PRIMARY'), \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole::Primary))->name->value // => 'PRIMARY'
 */
final class IndexIdentity
{
    use Snapshot;

    /**
     * Establishes the physical index coordinate and its key policy.
     */
    public function __construct(
        public readonly TableIdentity $owner,
        public readonly Identifier $name,
        public readonly IndexRole $role,
    ) {
        Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $name->value) === 1, 'An index name has 1 to 64 characters and no trailing space.');
        Check::input(
            $role === IndexRole::Primary ? $name->value === 'PRIMARY' : (new NativeIdentifierCase())->fold($name->value) !== 'primary',
            'Only the physical primary key has the canonical PRIMARY index identity.'
        );
    }
}
