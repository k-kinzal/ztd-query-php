<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A DO input requests a wildcard although it has no input relations.
 *
 * The qualifier identifies the rejected table expansion. The routine flag
 * retains the earlier dependency on a default database, without retaining SQL,
 * a parser node or an invalid executable statement.
 *
 * @visibility public
 * @example Describing an unqualified DO wildcard
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard())->message() // => 'No tables used'
 */
final class EvaluationWildcard implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName|null $table The wildcard qualifier; null for a bare star
     * @param bool $requiresDatabase Whether an unqualified stored call needs the default database
     */
    public function __construct(public readonly ?QualifiedName $table = null, public readonly bool $requiresDatabase = false)
    {
    }

    /**
     * Describes the wildcard that DO cannot expand.
     */
    public function message(): string
    {
        return $this->table === null ? 'No tables used' : "Unknown table '" . ($this->table->schema === null ? '' : $this->table->schema->value . '.') . $this->table->name->value . "'";
    }
}
