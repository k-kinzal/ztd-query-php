<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RejectedPasswordHash values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash>
 */
final class RejectedPasswordHashGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash> */
    use Composition;

    /**
     * Constructs the operand with no additional inputs.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (bool $unused): \SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash => new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash(), Generators::constant(false));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\RejectedPasswordHash' => [\SqlSemantics\Contract\GrammarRelease::MySql5744, new RejectedPasswordHashGenerator()];
    }

}
