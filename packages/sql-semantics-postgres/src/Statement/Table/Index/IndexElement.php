<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Index;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;

/**
 * One key of an index, an exclusion constraint or an ON CONFLICT target: a column or expression with its collation, operator class and ordering.
 *
 * Mirrors PostgreSQL's `IndexElem` (`name`/`expr`, `collation`, `opclass`,
 * `opclassopts`, `ordering`, `nulls_ordering`). The key is derived in the
 * environment of the owner, where the indexed relation is visible.
 * Source: https://www.postgresql.org/docs/17/sql-createindex.html.
 *
 * @visibility SqlSemantics
 */
final class IndexElement implements Clause
{
    use Snapshot;

    /**
     * @var list<Definition> The parameters of the operator class
     */
    public readonly array $classOptions;

    /**
     * @param ColumnKey|ExpressionKey $key The column or expression
     * @param DottedName|null $collation The collation
     * @param DottedName|null $operatorClass The operator class
     * @param list<Definition> $classOptions The parameters of the operator class; they need an operator class
     * @param SortDirection|null $direction The ordering, when written
     * @param NullsOrder|null $nulls The placement of NULL values, when written
     */
    public function __construct(
        public readonly ColumnKey|ExpressionKey $key,
        public readonly ?DottedName $collation = null,
        public readonly ?DottedName $operatorClass = null,
        array $classOptions = [],
        public readonly ?SortDirection $direction = null,
        public readonly ?NullsOrder $nulls = null,
    ) {
        $this->classOptions = Check::listOf($classOptions, Definition::class, 'Operator class parameters are definitions.');
        Check::input($this->classOptions === [] || $operatorClass !== null, 'Operator class parameters follow an operator class.');
    }

    /**
     * Derives the key and the operator class parameters.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->key, $environment);
        foreach ($this->classOptions as $option) {
            $option->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the key and its options.
     */
    public function render(Output $out): void
    {
        $out->node($this->key);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
        $out->node($this->operatorClass);
        (new Writing())->definitions($out, $this->classOptions);
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
        if ($this->nulls !== null) {
            $out->keyword('NULLS', $this->nulls->value);
        }
    }
}
