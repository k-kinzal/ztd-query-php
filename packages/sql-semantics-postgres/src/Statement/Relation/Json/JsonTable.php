<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\TableFunctionShapes;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * JSON_TABLE as a FROM item: rows and columns read from a JSON value (PostgreSQL 17).
 *
 * Mirrors PostgreSQL's `JsonTable`. The facts follow PG-JSON-TABLE-001.
 * Like a function call, it may refer to the FROM items before it whether or
 * not LATERAL is written; the word is kept as written.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Reading the columns of JSON_TABLE
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2'))->analyze("SELECT * FROM JSON_TABLE ('[]', '$[*]' AS p COLUMNS (n FOR ORDINALITY, e boolean EXISTS)) AS j");
 *     [$query->statement->from->pathName->value, $query->field(0)->type->descriptor->name(), $query->field(1)->name->value] // => ['p', 'integer', 'e']
 */
final class JsonTable implements Relation
{
    use Snapshot;

    /**
     * @var list<Clause> The PASSING arguments
     */
    public readonly array $passing;

    /**
     * @var non-empty-list<JsonTableColumn> The columns in written order
     */
    public readonly array $columns;

    /**
     * @var list<Name> The column names written after the correlation name
     */
    public readonly array $aliases;

    /**
     * @param Clause $context The JSON value the rows are read from, with its FORMAT clause
     * @param Scalar $path The row path, which PostgreSQL requires to be a string constant
     * @param list<JsonTableColumn> $columns The columns in written order; at least one
     * @param Name|null $pathName The name of the row path
     * @param list<Clause> $passing The PASSING arguments
     * @param Clause|null $onError The ON ERROR behavior
     * @param Name|null $alias The correlation name
     * @param list<Name> $aliases The column names written after the correlation name
     * @param bool $lateral Whether LATERAL is written
     */
    public function __construct(
        public readonly Clause $context,
        public readonly Scalar $path,
        array $columns,
        public readonly ?Name $pathName = null,
        array $passing = [],
        public readonly ?Clause $onError = null,
        public readonly ?Name $alias = null,
        array $aliases = [],
        public readonly bool $lateral = false,
    ) {
        $this->columns = Check::listOf($columns, JsonTableColumn::class, 'JSON_TABLE has at least one column.', 1);
        $this->passing = Check::listOf($passing, Clause::class, 'PASSING holds arguments.');
        $this->aliases = Check::listOf($aliases, Name::class, 'Column aliases are names.');
        Check::input($alias !== null || $this->aliases === [], 'Column aliases are written after a correlation name.');
    }

    /**
     * Derives the expressions and the columns of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new TableFunctionShapes())->json($this, $derivation, $environment);
    }

    /**
     * Writes LATERAL, JSON_TABLE with its arguments and columns, and the alias.
     */
    public function render(Output $out): void
    {
        if ($this->lateral) {
            $out->keyword('LATERAL');
        }
        $out->keyword('JSON_TABLE')->symbol('(')->node($this->context)->symbol(',')->node($this->path);
        if ($this->pathName !== null) {
            $out->keyword('AS')->name($this->pathName, NameUse::Column);
        }
        if ($this->passing !== []) {
            $out->keyword('PASSING')->list($this->passing);
        }
        $out->keyword('COLUMNS')->symbol('(')->list($this->columns)->symbol(')')->node($this->onError)->symbol(')');
        (new AliasSpelling())->write($out, $this->alias, $this->aliases);
    }
}
