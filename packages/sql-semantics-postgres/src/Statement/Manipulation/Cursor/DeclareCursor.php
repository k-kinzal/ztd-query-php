<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\CursorFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * DECLARE: a cursor over the rows of a query.
 *
 * Mirrors PostgreSQL's `DeclareCursorStmt` (portalname, options, query). The
 * facts follow PG-CURSOR-001.
 * Source: https://www.postgresql.org/docs/17/sql-declare.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a cursor declaration
 *     $declare = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DECLARE c SCROLL CURSOR WITHOUT HOLD FOR SELECT 1');
 *     [$declare->statement->name->value, $declare->toString()] // => ['c', 'DECLARE c SCROLL CURSOR WITHOUT HOLD FOR SELECT 1']
 */
final class DeclareCursor implements Statement
{
    use Snapshot;

    /**
     * @var list<CursorOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param Name $name The cursor name
     * @param list<CursorOption> $options The options in written order
     * @param Holdability|null $hold WITH HOLD or WITHOUT HOLD, when written
     * @param Query $query The query: a query statement
     *
     * @throws InvalidConstruction When the query is not a query statement, or an option is not a cursor option
     */
    public function __construct(public readonly Name $name, array $options, public readonly ?Holdability $hold, public readonly Query $query)
    {
        $this->options = Check::listOf($options, CursorOption::class, 'The options of DECLARE are cursor options.');
        Check::input((new Sources())->statement($query), 'A cursor reads the rows of a query statement.');
    }

    /**
     * Derives the query and checks the options.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new CursorFacts())->declare($this, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DECLARE')->name($this->name, NameUse::Column);
        foreach ($this->options as $option) {
            $out->keyword(...explode(' ', $option->value));
        }
        $out->keyword('CURSOR');
        if ($this->hold !== null) {
            $out->keyword($this->hold->value, 'HOLD');
        }
        $out->keyword('FOR')->node($this->query);
    }
}
