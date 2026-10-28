<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * `TRUNCATE [TABLE] t`: a request to remove every row of a table.
 *
 * Mirrors PT_truncate_table_stmt. Rule: MYSQL-TRUNCATE-001. The name
 * resolves by MYSQL-CHANGE-TARGET-001 and its resolution is the relation fact
 * of the statement node; a declared view is refused
 * (MYSQL-RELATION-KIND-001). The statement changes no declaration and provides
 * none. The optional TABLE keyword is retained.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/truncate-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Resolving the emptied table
 *     $truncate = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('TRUNCATE shop.t');
 *     [$truncate->toString(), $truncate->table->schema?->value] // => ['TRUNCATE shop.t', 'shop']
 */
final class TruncateTable extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param QualifiedName $table The table with its optional database
     * @param \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $tableWord Whether TABLE is written
     */
    public function __construct(public readonly QualifiedName $table, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\Fact\RelationFact $relationInformation = null, public readonly \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $tableWord = \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($table);
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records the resolution of the table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->target($this, (new Targets())->target($derivation, $this->table));
        (new RelationKinds())->refuseView($derivation, $this->table, $fact->table, KindRefusal::NoSuchTable);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('TRUNCATE');
        if ($this->tableWord === \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written) {
            $out->keyword('TABLE');
        }
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
    }
}
