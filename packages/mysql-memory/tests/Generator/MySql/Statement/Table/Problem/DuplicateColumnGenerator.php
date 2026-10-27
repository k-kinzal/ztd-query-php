<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DuplicateColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn>
 */
final class DuplicateColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     */
    public function __construct(ErisGenerator $column)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn(column: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $column
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\DuplicateColumn' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new DuplicateColumnGenerator(
            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 'a' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            )
        )];
    }

}
