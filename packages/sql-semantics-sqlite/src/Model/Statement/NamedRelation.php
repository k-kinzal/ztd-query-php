<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement;

use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;

/**
 * An input relation that is one named table, view or common table, optionally renamed.
 *
 * @visibility SqlSemantics
 */
interface NamedRelation extends Relation
{
    /**
     * Answers the relation name as the statement wrote it, decoded.
     */
    public function name(): QualifiedName;

    /**
     * Answers the correlation name that renames the occurrence, when one is given.
     */
    public function alias(): ?Name;
}
