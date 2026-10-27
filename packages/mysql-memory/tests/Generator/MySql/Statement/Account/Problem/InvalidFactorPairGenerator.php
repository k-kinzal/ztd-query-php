<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidFactorPair values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair>
 */
final class InvalidFactorPairGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $identical Generated constructor operand
     */
    public function __construct(ErisGenerator $identical)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair(identical: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $identical
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\InvalidFactorPair' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidFactorPairGenerator(
            identical: Generators::constant(false)
        )];
    }

}
