<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index;

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
use SqlSemantics\Statement\Snapshot;

/**
 * A complete index instruction whose name or key condition is checked when invoked.
 *
 * The target and ordered operands are retained for binding and native error priority.
 * This instruction is not an ordinary Statement and holds no SQL or parser node.
 *
 * @visibility public
 * @example Keeping a stored index instruction until invocation
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE INDEX i ON t ((ROW(1, 2)))');
 *     $operation->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex // => true
 */
final class DeferredCreateIndex implements \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement, Relation
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
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($table);
        Check::input($table->catalog === null, 'A table name has at most a database qualifier.');
        Check::input($kind !== IndexKind::Primary, 'CREATE INDEX does not create a primary key.');
        Check::input($algorithm === null || $kind === IndexKind::Index || $kind === IndexKind::Unique, 'A full-text or spatial index has no USING clause before ON.');
        $this->parts = Check::listOf($parts, KeyPart::class, 'An index has at least one key part.', 1);
        $this->options = Check::listOf($options, IndexOption::class, 'Index options are an ordered list of index options.');
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\IndexOptions())->check($kind, $this->options);
        Check::input((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->request($name, $this->parts), 'A retained index instruction requires a fixed name or operand condition.');
        $this->alterOptions = (new \SqlSemantics\Platform\MySql\Rules\TableChange\IndexOptions())->checked($alterOptions);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Resolves the indexed table, derives the key parts against it and checks the ALGORITHM and LOCK clauses.
     */
    public function deriveProgram(Derivation $derivation, \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope $scope): void
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
