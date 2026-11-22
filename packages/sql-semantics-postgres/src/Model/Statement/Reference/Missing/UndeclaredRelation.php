<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A relation an open context has no declaration for.
 *
 * @visibility SqlSemantics
 * @example Naming an undeclared relation
 *     $name = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t'));
 *     (new \SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\UndeclaredRelation($name))->describe() // => 'the declaration of relation t'
 */
final class UndeclaredRelation implements MissingInput
{
    use Snapshot;

    /**
     * @param QualifiedName $name The name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Describes the missing declaration.
     */
    public function describe(): string
    {
        $parts = array_filter([$this->name->catalog?->value, $this->name->schema?->value, $this->name->name->value], static fn (?string $part): bool => $part !== null);

        return 'the declaration of relation ' . implode('.', $parts);
    }
}
