<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to change the attributes of a composite type.
 *
 * Rule: PG-TYPE-COMPOSITE-002. Mirrors `AlterTableStmt` with object type
 * TYPE: the changes in the order written. The statement changes no
 * declaration (a composite type is not a relation of the context).
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterComposite implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<AttributeChange> The changes in order
     */
    public readonly array $changes;

    /**
     * @param DottedName $name The type name
     * @param list<AttributeChange> $changes The changes in order; at least one
     */
    public function __construct(public readonly DottedName $name, array $changes)
    {
        $this->changes = Check::listOf($changes, AttributeChange::class, 'ALTER TYPE changes at least one attribute.', 1);
    }

    /**
     * Derives the types of the changes.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->changes);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TYPE')->node($this->name)->list($this->changes);
    }
}
