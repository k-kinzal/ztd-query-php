<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Problem\Insert;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedInsertRows values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertRows>
 */
final class RefusedInsertRowsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertRows> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows> $write Generated constructor operand
     */
    public function __construct(ErisGenerator $write)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertRows {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertRows(write: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $write
        ));
    }
    /**
     * Generates an input refusal with all of its retained operand dependencies.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertRows>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Dml/Problem/Insert/RefusedInsertRows' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new self(
            new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertRowsGenerator(
                new \Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator(
                    Generators::constant(false),
                    Generators::constant(null),
                    Generators::constant(false),
                    new \Tests\Generator\MySql\Statement\Dml\WriteTargetGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator())),
                ),
                Generators::vector(1, new \Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertedRowGenerator(Generators::vector(
                    1,
                    new \Tests\Generator\MySql\Statement\Expression\RowGenerator(Generators::vector(2, new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))))),
                ))),
            ),
        )];
    }

}
