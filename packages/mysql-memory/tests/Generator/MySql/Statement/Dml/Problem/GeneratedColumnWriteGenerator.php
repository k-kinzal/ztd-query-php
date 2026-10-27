<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GeneratedColumnWrite values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite>
 */
final class GeneratedColumnWriteGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $table Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ErisGenerator $table)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite(column: $parts[0], table: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $table
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml\\Problem\\GeneratedColumnWrite' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new GeneratedColumnWriteGenerator(
            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'g' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            table: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            )
        )];
    }

}
