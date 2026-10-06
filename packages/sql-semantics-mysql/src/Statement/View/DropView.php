<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to drop views.
 *
 * Rule: MYSQL-DROP-VIEW-001. Each name resolves by CORE-TABLE-LOOKUP-001 in
 * the current database or the database written. A name a complete context
 * does not declare, or that conflicts, is a diagnostic, except that IF
 * EXISTS allows an absent view (the server only adds a note). A declared
 * base table is refused by MYSQL-RELATION-KIND-001: always in 8.1, 8.2, 8.3,
 * 9.0 and 9.1, and in the other releases only without IF EXISTS. The
 * statement changes no context. RESTRICT and CASCADE are parsed and ignored
 * by the server; they are kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-view.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the views to drop
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DROP VIEW IF EXISTS v, shop.w');
 *     [count($drop->statement->views), $drop->statement->ifExists] // => [2, true]
 */
final class DropView implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<QualifiedName> The views in order
     */
    public readonly array $views;

    /**
     * @param list<QualifiedName> $views The views in order; at least one
     * @param bool $ifExists Whether IF EXISTS is written
     * @param DropBehavior|null $behavior RESTRICT or CASCADE, when written
     */
    public function __construct(array $views, public readonly bool $ifExists = false, public readonly ?DropBehavior $behavior = null)
    {
        $this->views = Check::listOf($views, QualifiedName::class, 'DROP VIEW names at least one view.', 1);
        foreach ($this->views as $view) {
            Check::input($view->catalog === null, 'A view name has at most a database qualifier.');
        }
    }

    /**
     * Resolves the views and reports the names the server refuses.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $kinds = new RelationKinds();
        $refused = !$this->ifExists || !in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847], true);
        foreach ($this->views as $view) {
            $resolution = $derivation->table($view, $derivation->environment());
            if ($resolution instanceof Diagnostic && !($this->ifExists && $resolution instanceof MissingTable)) {
                $derivation->report($resolution);
            }
            if ($refused) {
                $kinds->require($derivation, $view, $resolution, RelationKind::View);
            }
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'VIEW');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        foreach ($this->views as $position => $view) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($view->schema !== null) {
                $out->name($view->schema, NameUse::Qualifier)->symbol('.');
            }
            $out->name($view->name, NameUse::Relation);
        }
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
