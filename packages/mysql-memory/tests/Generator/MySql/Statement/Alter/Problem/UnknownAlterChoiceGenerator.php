<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnknownAlterChoice values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice>
 */
final class UnknownAlterChoiceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $lock Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $lock, ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice(lock: $parts[0], name: $parts[1]);
        }, Generators::tuple(
            $lock,
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Alter\\Problem\\UnknownAlterChoice' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnknownAlterChoiceGenerator(
            lock: Generators::constant(false),
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('FAST'),
                quote: \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator::selecting([\SqlSemantics\Statement\Identifier\NameQuote::Bare])
            )
        )];
    }

}
