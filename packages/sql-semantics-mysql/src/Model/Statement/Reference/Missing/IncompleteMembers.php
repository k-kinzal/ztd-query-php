<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing;

use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A declared relation whose member list the context marks as incomplete.
 *
 * @visibility SqlSemantics
 * @example Describing an incomplete member list
 *     $profile = (new \SqlSemantics\Platform\MySql\Semantics())->profile();
 *     $table = new \SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('t')), $profile, [], [], false);
 *     (new \SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\IncompleteMembers($table))->describe() // => 'the complete column list of relation t'
 */
final class IncompleteMembers implements MissingInput
{
    use Snapshot;

    /**
     * @param Table $table The declaration with an incomplete column list
     */
    public function __construct(public readonly Table $table)
    {
    }

    /**
     * Describes the missing members.
     */
    public function describe(): string
    {
        return 'the complete column list of relation ' . $this->table->name->name->value;
    }
}
