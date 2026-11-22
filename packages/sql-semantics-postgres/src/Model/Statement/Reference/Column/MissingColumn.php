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
 * @visibility SqlSemantics
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
