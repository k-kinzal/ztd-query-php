<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
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
 * XMLTABLE as a FROM item: rows and columns read from an XML value.
 *
 * Mirrors PostgreSQL's `RangeTableFunc`. The facts follow PG-XMLTABLE-001.
 * Like a function call, it may refer to the FROM items before it whether or
 * not LATERAL is written; the word is kept as written.
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PROCESSING-XMLTABLE. Status: Implemented.
 *
 * @visibility public
 * @example Reading the columns of XMLTABLE
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text PATH 'a' NOT NULL) AS x");
 *     [$query->field(0)->type->descriptor->name(), $query->field(1)->nullability->name] // => ['integer', 'NotNull']
 */
final class XmlTable implements Relation
{
    use Snapshot;

    /**
     * @var list<XmlNamespace> The namespaces of XMLNAMESPACES
     */
    public readonly array $namespaces;

    /**
     * @var non-empty-list<XmlTableColumn> The columns in written order
     */
    public readonly array $columns;

    /**
     * @var list<Name> The column names written after the correlation name
     */
    public readonly array $aliases;

    /**
     * @param Scalar $row The XPath expression that yields the rows, a primary expression
     * @param Clause $passing The PASSING clause with the document
     * @param list<XmlTableColumn> $columns The columns in written order; at least one
     * @param list<XmlNamespace> $namespaces The namespaces of XMLNAMESPACES
     * @param Name|null $alias The correlation name
     * @param list<Name> $aliases The column names written after the correlation name
     * @param bool $lateral Whether LATERAL is written
     */
    public function __construct(
        public readonly Scalar $row,
        public readonly Clause $passing,
        array $columns,
        array $namespaces = [],
        public readonly ?Name $alias = null,
        array $aliases = [],
        public readonly bool $lateral = false,
    ) {
        $this->columns = Check::listOf($columns, XmlTableColumn::class, 'XMLTABLE has at least one column.', 1);
        $this->namespaces = Check::listOf($namespaces, XmlNamespace::class, 'XMLNAMESPACES holds namespaces.');
        $this->aliases = Check::listOf($aliases, Name::class, 'Column aliases are names.');
        Check::input($alias !== null || $this->aliases === [], 'Column aliases are written after a correlation name.');
        Check::input((new Precedence())->primary($row), 'The row expression of XMLTABLE is a primary expression.');
    }

    /**
     * Derives the expressions and the columns of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new TableFunctionShapes())->xml($this, $derivation, $environment);
    }

    /**
     * Writes LATERAL, XMLTABLE with its namespaces, row expression, PASSING clause and columns, and the alias.
     */
    public function render(Output $out): void
    {
        if ($this->lateral) {
            $out->keyword('LATERAL');
        }
        $out->keyword('XMLTABLE')->symbol('(');
        if ($this->namespaces !== []) {
            $out->keyword('XMLNAMESPACES')->symbol('(')->list($this->namespaces)->symbol(')')->symbol(',');
        }
        $out->node($this->row)->node($this->passing)->keyword('COLUMNS')->list($this->columns)->symbol(')');
        (new AliasSpelling())->write($out, $this->alias, $this->aliases);
    }
}
