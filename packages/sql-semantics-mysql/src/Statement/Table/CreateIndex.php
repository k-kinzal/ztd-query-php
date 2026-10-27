<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;

/**
 * A request to add an index to an existing table: CREATE [UNIQUE | FULLTEXT | SPATIAL] INDEX.
 *
 * Rule: MYSQL-CREATE-INDEX-001. The statement node is the one occurrence of
 * the indexed table: its relation fact is the resolution of the table name
 * (MYSQL-DEFINITION-SCOPE-001), which is no declared view
 * (MYSQL-RELATION-KIND-001), and the functional key parts are derived at
 * a position whose only visible relation is that table. A key column the
 * declared table lacks is a diagnostic (ER_KEY_COLUMN_DOES_NOT_EXITS). The
 * ALGORITHM and LOCK clauses come from the table change family. Indexes are
 * not part of a declaration context: the statement provides no declaration.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html. Status: Implemented.
 *
 * @visibility public
 * @example Resolving the indexed table
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
 *     $index = $semantics->analyze('CREATE UNIQUE INDEX i ON t (b DESC, (a + b))', [$table]);
 *     [$index->relationInformation()->table->table === $table->declarations()[0], $index->diagnostics()] // => [true, []]
 */
final class CreateIndex extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, Relation, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<KeyPart> The key parts in order
     */
    public readonly array $parts;

    /**
     * @var list<IndexOption> The options after the key parts, in written order
     */
    public readonly array $options;

    /**
     * @var list<AlterOption> The ALGORITHM and LOCK clauses in written order
     */
    public readonly array $alterOptions;

    /**
     * @param Name $name The index name
     * @param QualifiedName $table The indexed table
     * @param list<KeyPart> $parts The key parts in order; at least one
     * @param IndexKind $kind INDEX, UNIQUE, FULLTEXT or SPATIAL
     * @param IndexAlgorithm|null $algorithm The USING clause written before ON
     * @param list<IndexOption> $options The options after the key parts, in written order
     * @param list<AlterOption> $alterOptions The ALGORITHM and LOCK clauses in written order
     */
    public function __construct(
        public readonly Name $name,
        public readonly QualifiedName $table,
        array $parts,
        public readonly IndexKind $kind = IndexKind::Index,
        public readonly ?IndexAlgorithm $algorithm = null,
        array $options = [],
        array $alterOptions = [],
        ?RelationFact $relationInformation = null,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($table);
        Check::input($table->catalog === null, 'A table name has at most a database qualifier.');
        Check::input($kind !== IndexKind::Primary, 'CREATE INDEX does not create a primary key.');
        Check::input($algorithm === null || $kind === IndexKind::Index || $kind === IndexKind::Unique, 'A full-text or spatial index has no USING clause before ON.');
        $this->parts = Check::listOf($parts, KeyPart::class, 'An index has at least one key part.', 1);
        $this->options = Check::listOf($options, IndexOption::class, 'Index options are an ordered list of index options.');
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\IndexOptions())->check($kind, $this->options);
        $this->alterOptions = (new \SqlSemantics\Platform\MySql\Rules\TableChange\IndexOptions())->checked($alterOptions);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Identifies a retained name or key condition whose priority depends on target lookup.
     */
    public function hasExecutionCondition(): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->request($this->name, $this->parts, $this->kind);
    }

    /**
     * Resolves the indexed table, derives the key parts against it and checks the ALGORITHM and LOCK clauses.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Requests())->derive($this, $derivation);
    }

    /**
     * Resolves the indexed table and answers its row shape.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new TableTargets())->existing($derivation, $this->table);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Requests())->render($this, $out);
    }
}
