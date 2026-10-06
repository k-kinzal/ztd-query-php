<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\View;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to replace the rows of a materialized view.
 *
 * Mirrors PostgreSQL's `RefreshMatViewStmt` (`concurrent`, `skipData`,
 * `relation`). The view is resolved (PG-TABLE-TARGET-001) and is the
 * relation fact of the statement; the statement changes no declaration. A
 * relation declared as another kind is reported (PG-RELATION-KIND-001).
 * Source: https://www.postgresql.org/docs/17/sql-refreshmaterializedview.html.
 *
 * @visibility public
 * @example Resolving the refreshed view
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REFRESH MATERIALIZED VIEW CONCURRENTLY m WITH DATA', []);
 *     [$statement->facts->diagnostics[0]->message(), $statement->toString()] // => ['Relation m does not exist.', 'REFRESH MATERIALIZED VIEW CONCURRENTLY m WITH DATA']
 */
final class RefreshMaterializedView implements Statement, Relation
{
    use Snapshot;

    /**
     * @param QualifiedName $name The materialized view
     * @param bool $concurrently Whether CONCURRENTLY is written
     * @param bool|null $withData True for WITH DATA, false for WITH NO DATA, null when not written
     */
    public function __construct(public readonly QualifiedName $name, public readonly bool $concurrently = false, public readonly ?bool $withData = null)
    {
    }

    /**
     * Resolves the view and reports a relation that is declared as another kind (PG-RELATION-KIND-001).
     *
     * A table passes the lookup and is then refused as not a materialized
     * view; a view, a sequence or a foreign table is refused by the lookup.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $kinds = new RelationKinds();
        $kind = $kinds->of($derivation->relation($this, $derivation->environment()));
        $kinds->require($derivation, $kind, $this->name->name, [RelationKind::MaterializedView], $kind === RelationKind::BaseTable ? KindRule::NotMaterializedView : KindRule::NotTableOrMaterializedView);
    }

    /**
     * Resolves the view and answers its facts.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new Targets())->resolve($derivation, $this->name);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('REFRESH', 'MATERIALIZED', 'VIEW');
        if ($this->concurrently) {
            $out->keyword('CONCURRENTLY');
        }
        (new Spelling())->qualified($out, $this->name);
        (new Writing())->withData($out, $this->withData);
    }
}
