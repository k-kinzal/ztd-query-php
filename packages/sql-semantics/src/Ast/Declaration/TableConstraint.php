<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Declaration;

use SqlParser\Parser\Node;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\Schema\ReferentialAction;

/**
 * Internal declaration syntax awaiting name and type binding.
 *
 * @visibility SqlSemantics
 */
final class TableConstraint
{
    /**
     * @param ConstraintKind $kind Integrity condition
     * @param list<string> $columns Local columns, in declaration order
     * @param Node $source Complete constraint syntax, including actions and deferrability
     * @param string|null $name Explicit constraint name
     * @param list<string> $referencedTable Qualified foreign table name
     * @param list<string> $referencedColumns Referenced key columns; empty means the primary key
     * @param Node|null $expression CHECK expression syntax
     * @param ReferentialAction $onDelete Action when the referenced row is deleted
     * @param ReferentialAction $onUpdate Action when the referenced key is updated
     * @param string $match Foreign-key matching mode
     * @param bool $deferrable Whether constraint checking can be deferred
     * @param bool $initiallyDeferred Whether checking starts deferred
     * @param list<string> $deleteColumns Explicit columns affected by an ON DELETE SET action
     */
    public function __construct(
        public readonly ConstraintKind $kind,
        public readonly array $columns,
        public readonly Node $source,
        public readonly ?string $name = null,
        public readonly array $referencedTable = [],
        public readonly array $referencedColumns = [],
        public readonly ?Node $expression = null,
        public readonly ReferentialAction $onDelete = ReferentialAction::NoAction,
        public readonly ReferentialAction $onUpdate = ReferentialAction::NoAction,
        public readonly string $match = 'simple',
        public readonly bool $deferrable = false,
        public readonly bool $initiallyDeferred = false,
        public readonly array $deleteColumns = [],
    ) {
    }
}
