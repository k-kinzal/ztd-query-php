<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedPurgeOperand values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedPurgeOperand>
 */
final class RefusedPurgeOperandGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedPurgeOperand> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $moment Generated constructor operand
     * @param ErisGenerator<bool>|null $masterLogs Generated legacy word choice
     */
    public function __construct(ErisGenerator $moment, ?ErisGenerator $masterLogs = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedPurgeOperand {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedPurgeOperand(moment: $parts[0], masterLogs: $parts[1]);
        }, Generators::tuple(
            $moment,
            $masterLogs ?? Generators::bool()
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedPurgeOperand>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Replication\\Problem\\RefusedPurgeOperand' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedPurgeOperandGenerator(
            masterLogs: Generators::constant(false),
            moment: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                elements: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                        text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
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
