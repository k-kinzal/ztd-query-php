<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedKillOperand values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand>
 */
final class RefusedKillOperandGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $process Generated constructor operand
     */
    public function __construct(ErisGenerator $process)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand(process: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $process
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Problem\\RefusedKillOperand' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedKillOperandGenerator(
            process: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                elements: \Tests\Generator\Operands::tuple(
                    new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $suffix): string => 'missing' . $suffix, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        qualifier: Generators::constant(null),
                        dot: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                    )])
                ),
                keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
            )])
        )];
    }

}
