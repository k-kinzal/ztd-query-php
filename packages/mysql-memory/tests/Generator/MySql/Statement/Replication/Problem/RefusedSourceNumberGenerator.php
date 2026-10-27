<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedSourceNumber values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber>
 */
final class RefusedSourceNumberGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $number Generated constructor operand
     * @param ErisGenerator<int> $offset Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $number, ErisGenerator $offset)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber(kind: $parts[0], number: $parts[1], offset: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $number,
            $offset
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Replication\\Problem\\RefusedSourceNumber' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedSourceNumberGenerator(
            kind: \Tests\Generator\MySql\Statement\Replication\Source\SourceOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::RequireRowFormat]),
            number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('100000000'),
                hexadecimal: Generators::constant(true)
            ),
            offset: Generators::constant(0)
        )];
    }

}
