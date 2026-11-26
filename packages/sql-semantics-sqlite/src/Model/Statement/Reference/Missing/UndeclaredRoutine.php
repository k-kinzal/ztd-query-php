<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing;

use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A function or procedure whose signature is neither fixed by the language profile nor declared in the context.
 *
 * @visibility SqlSemantics
 * @example Naming an undeclared routine
 *     $name = new \SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name('score'));
 *     (new \SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRoutine($name))->describe() // => 'the signature of routine score'
 */
final class UndeclaredRoutine implements MissingInput
{
    use Snapshot;

    /**
     * @param QualifiedName $name The routine name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Describes the missing signature.
     */
    public function describe(): string
    {
        return 'the signature of routine ' . $this->name->name->value;
    }
}
