<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A request SQLite refuses because the relation it names is of another kind.
 *
 * Source: https://sqlite.org/lang_dropview.html, https://sqlite.org/lang_createtrigger.html#instead_of_triggers.
 *
 * @visibility public
 * @example Reporting DROP TABLE of a view
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $view = $semantics->analyze('CREATE VIEW v AS SELECT 1');
 *     $semantics->analyze('DROP TABLE v', [$view])->facts->diagnostics[0]->message() // => 'Relation v is a view: DROP TABLE removes only a table.'
 */
final class WrongRelationKind implements Diagnostic
{
    use Snapshot;

    /**
     * @param Table $relation The declaration the name resolved to
     * @param KindRefusal $refusal The request and the kind it needs
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the relation is of the kind the request needs
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
