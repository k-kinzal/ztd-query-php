<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedSetting values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting>
 */
final class RefusedSettingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError> $error Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $number Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $text Generated constructor operand
     */
    public function __construct(ErisGenerator $error, ?ErisGenerator $number = null, ?ErisGenerator $text = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting(error: $parts[0], number: $parts[1], text: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $error,
            $number ?? Generators::constant(null),
            $text ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Replication\\Problem\\RefusedSetting' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedSettingGenerator(
            error: ReplicationErrorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::PasswordTooLong]),
            number: Generators::constant(null),
            text: Generators::constant(null)
        )];
    }

}
