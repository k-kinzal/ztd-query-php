<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidEncryption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption>
 */
final class InvalidEncryptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $value Generated constructor operand
     */
    public function __construct(ErisGenerator $value)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption(value: $parts[0]);
        }, Generators::tuple(
            $value
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Database\\Problem\\InvalidEncryption' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new InvalidEncryptionGenerator(
            value: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('bad'),
                radix: Generators::constant(null)
            )
        )];
    }

}
