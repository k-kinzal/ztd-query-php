<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AccountNameFailure values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure>
 */
final class AccountNameFailureGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure> */
    use Composition;

    /**
     * @param ErisGenerator<string> $operand Generated constructor operand
     * @param ErisGenerator<bool> $host Generated constructor operand
     * @param ErisGenerator<int|null>|null $limit Generated constructor operand
     */
    public function __construct(ErisGenerator $operand, ErisGenerator $host, ?ErisGenerator $limit = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure(operand: $parts[0], host: $parts[1], limit: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $operand,
            $host,
            $limit ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Account\\Problem\\AccountNameFailure' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new AccountNameFailureGenerator(
            operand: Generators::constant('bad@host'),
            host: Generators::constant(true),
            limit: Generators::constant(null)
        )];
    }

}
