<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnattachedEnforcement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement>
 */
final class UnattachedEnforcementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement> */
    use Composition;

    /**
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement(offset: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $offset
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\UnattachedEnforcement' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnattachedEnforcementGenerator(
            offset: Generators::constant(12)
        )];
    }

}
