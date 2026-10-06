<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

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
 *     [$index->facts->relation($index->statement)->table->table === $table->declarations()[0], $index->facts->diagnostics] // => [true, []]
 */
final class CreateIndex implements Statement, Relation
{
    use Snapshot;

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
    ) {
        Check::input($table->catalog === null, 'A table name has at most a database qualifier.');
        Check::input($kind !== IndexKind::Primary, 'CREATE INDEX does not create a primary key.');
        Check::input($algorithm === null || $kind === IndexKind::Index || $kind === IndexKind::Unique, 'A full-text or spatial index has no USING clause before ON.');
        $this->parts = Check::listOf($parts, KeyPart::class, 'An index has at least one key part.', 1);
        $this->options = Check::listOf($options, IndexOption::class, 'Index options are an ordered list of index options.');
        $this->alterOptions = Check::listOf($alterOptions, AlterOption::class, 'ALGORITHM and LOCK clauses are an ordered list.');
    }

    /**
     * Resolves the indexed table, derives the key parts against it and checks the ALGORITHM and LOCK clauses.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $targets = new TableTargets();
        $fact = $derivation->relation($this, $derivation->environment());
        (new RelationKinds())->require($derivation, $this->table, $fact->table, RelationKind::BaseTable);
        $declared = $fact->table instanceof DeclaredTable ? $fact->table->table : null;
        $scope = $targets->scope($derivation, $this, new QualifiedName($this->table->name), $fact->shape, $declared === null ? [] : $targets->implicit($declared));
        foreach ($this->parts as $part) {
            $part->deriveKeyPart($derivation, $scope);
            if ($declared !== null && $declared->complete && $part instanceof ColumnPart && $declared->matchingColumns($part->column->value, $derivation->context->columnNames) === []) {
                $derivation->report(new UnknownKeyColumn($part->column));
            }
        }
        foreach ($this->alterOptions as $option) {
            $option->deriveOption($derivation);
        }
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
        $out->keyword('CREATE');
        if ($this->kind !== IndexKind::Index) {
            $out->keyword($this->kind->value);
        }
        $out->keyword('INDEX')->name($this->name, NameUse::Label);
        if ($this->algorithm !== null) {
            $out->keyword('USING', $this->algorithm->value);
        }
        $out->keyword('ON');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation)->symbol('(')->list($this->parts)->symbol(')');
        foreach ([...$this->options, ...$this->alterOptions] as $option) {
            $out->node($option);
        }
    }
}
