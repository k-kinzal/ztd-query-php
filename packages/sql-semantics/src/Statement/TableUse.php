<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Statement\Fact\RelationFact;

/**
 * A SQL occurrence whose table reference has a resolved relation shape.
 * This includes read inputs and table uses in definitions and other commands.
 *
 * @visibility public
 * @example Inspecting the destination of an INSERT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT t VALUES (1)', []);
 *     $insert->into->table->relationInformation()->table instanceof \SqlSemantics\Statement\Reference\Table\MissingTable // => true
 */
interface TableUse extends Node
{
    /**
 * Completes the table occurrence before publication.
 */
    public function initializeRelationInformation(RelationFact $information): void;

    /**
 * Returns the resolved shape and table identity owned by this occurrence.
 */
    public function relationInformation(): RelationFact;

    /**
 * Reports whether construction resolved this occurrence.
 */
    public function hasRelationInformation(): bool;
}
