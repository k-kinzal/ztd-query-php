<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A request SQLite refuses because the relation it names is of another kind.
 *
 * Source: https://sqlite.org/lang_dropview.html, https://sqlite.org/lang_createtrigger.html#instead_of_triggers.
 *
 * @visibility SqlSemantics
 */
final class WrongRelationKind implements Diagnostic
{
    use Snapshot;

    /**
     * @param Table $relation The declaration the name resolved to
     * @param KindRefusal $refusal The request and the kind it needs
     * @throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction When the relation is of the kind the request needs
     */
    public function __construct(public readonly Table $relation, public readonly KindRefusal $refusal)
    {
        Check::input($relation->kind !== $refusal->required(), 'A relation of the kind a request needs is not refused.');
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        $kind = match ($this->relation->kind) {
            RelationKind::BaseTable => 'table',
            RelationKind::View => 'view',
            RelationKind::MaterializedView => 'materialized view',
            RelationKind::ForeignTable => 'foreign table',
            RelationKind::Sequence => 'sequence',
        };

        return 'Relation ' . $this->relation->name->name->value . ' is a ' . $kind . ': ' . $this->refusal->value;
    }
}
