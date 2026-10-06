<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `STORAGE type`: the data type actually stored in an index of the operator class.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createopclass.html.
 *
 * @visibility public
 * @example Reading the storage type
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('int4')])));
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Operator\StorageMember($type))->type === $type // => true
 */
final class StorageMember implements OperatorClassItem
{
    use Snapshot;

    /**
     * @param TypeName $type The stored type
     */
    public function __construct(public readonly TypeName $type)
    {
    }

    /**
     * Derives the type modifiers.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes STORAGE and the type.
     */
    public function render(Output $out): void
    {
        $out->keyword('STORAGE')->node($this->type);
    }
}
