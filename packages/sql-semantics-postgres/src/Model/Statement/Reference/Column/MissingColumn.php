<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A name that no visible relation has, established by complete declarations.
 *
 * @visibility public
 * @example Establishing that a column does not exist
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $semantics->analyze('SELECT b FROM t', [$table]) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException: Column b does not exist.
 */
final class MissingColumn implements Resolution, Diagnostic
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param QualifiedName|null $qualifier The relation qualifier the use wrote
     */
    public function __construct(public readonly Name $name, public readonly ?QualifiedName $qualifier = null)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . ($this->qualifier === null ? '' : $this->qualifier->name->value . '.') . $this->name->value . ' does not exist.';
    }
}
