<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InvalidUpgradeName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName>
 */
final class InvalidUpgradeNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Database\\Problem\\InvalidUpgradeName' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, new InvalidUpgradeNameGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'ordinary' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            )
        )];
    }

}
