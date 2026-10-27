<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedColumns values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns>
 */
final class RefusedColumnsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $view Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName|null>|null $program Generated constructor operand
     */
    public function __construct(ErisGenerator $view, ErisGenerator $column, ?ErisGenerator $program = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns {
            return new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns(view: $parts[0], column: $parts[1], program: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $view,
            $column,
            $program ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns>}>
     */
    public static function executionForms(): iterable
    {
        yield 'View\\Problem\\RefusedColumns' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedColumnsGenerator(
            view: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'v' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            column: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant(''),
                quote: Generators::constant(null)
            ),
            program: Generators::constant(null)
        )];
    }

}
