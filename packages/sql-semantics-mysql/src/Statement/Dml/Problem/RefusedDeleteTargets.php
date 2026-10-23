<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A multiple-table DELETE with a target absent from its input namespace.
 *
 * Complete typed dependencies retain earlier input conditions. The enclosing
 * declaration supplies its database even for an unreachable stored instruction.
 * This refusal is not a statement and contains neither SQL text nor parser ASTs.
 *
 * @visibility public
 * @example Refusing a target outside the input namespace before an operation exists
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DELETE x FROM t AS y') // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedDeleteTargets implements Diagnostic
{
    use Snapshot;

    /**
     * @var non-empty-list<QualifiedName> The targets in written order
     */
    public readonly array $targets;

    /**
     * @var non-empty-list<TableSource> The complete input occurrences
     */
    public readonly array $tables;

    /**
     * The first target absent independently of database selection or name comparison.
     */
    public readonly QualifiedName $missing;

    /**
     * @param list<QualifiedName> $targets The requested targets
     * @param list<TableSource> $tables The input relation trees
     * @param WithClause|null $with The common table definitions
     * @param Scalar|null $where The complete row predicate
     * @param QualifiedName|null $program The enclosing declaration
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the supplied target namespace contradicts this role
     */
    public function __construct(array $targets, array $tables, public readonly ?WithClause $with = null, public readonly ?Scalar $where = null, public readonly ?QualifiedName $program = null)
    {
        $this->targets = Check::listOf($targets, QualifiedName::class, 'A refused DELETE retains its targets.', 1);
        $this->tables = Check::listOf($tables, TableSource::class, 'A refused DELETE retains its input occurrences.', 1);
        $missing = (new TargetReferences())->missing($this->targets, $this->tables);
        Check::input($missing !== null, 'A refused DELETE has a target absent from its input namespace.');
        $this->missing = $missing;
    }

    /**
     * Derives retained dependencies so that earlier input conditions remain observable.
     */
    public function derive(Derivation $derivation): void
    {
        $rules = new ChangeFacts();
        $base = $rules->base($this->with, $derivation, $derivation->environment());
        $visible = $rules->references($this->tables, $derivation, $base);
        $rules->targets($this->targets, $visible, $derivation);
        $rules->clauses($this->where, [], null, $derivation, $base, new Environment($derivation->context, $base, $visible));
    }

    /**
     * Describes the first intrinsically absent target.
     */
    public function message(): string
    {
        return (new UnknownDeleteTable($this->missing))->message();
    }
}
