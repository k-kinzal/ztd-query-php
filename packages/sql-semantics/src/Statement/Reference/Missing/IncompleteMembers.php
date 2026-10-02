<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Missing;

use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Snapshot;

/**
 * A declared relation whose member list the context marks as incomplete.
 *
 * @visibility public
 * @example Describing an incomplete member list
 *     $profile = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->profile();
 *     $table = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [], [], false);
 *     (new \SqlSemantics\Statement\Reference\Missing\IncompleteMembers($table))->describe() // => 'the complete column list of relation t'
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
