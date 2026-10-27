<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DatabaseOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>
 */
final class DatabaseOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
}
