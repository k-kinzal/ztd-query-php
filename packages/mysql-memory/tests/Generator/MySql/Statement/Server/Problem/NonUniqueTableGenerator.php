<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NonUniqueTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable>
 */
final class NonUniqueTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $alias Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\TableUse|null>|null $relation Generated constructor operand
     */
    public function __construct(ErisGenerator $alias, ?ErisGenerator $relation = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable(alias: $parts[0], relation: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $alias,
            $relation ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Server\\Problem\\NonUniqueTable' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new NonUniqueTableGenerator(
            alias: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $suffix): string => 't' . $suffix, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            relation: Generators::constant(null)
        )];
    }

}
