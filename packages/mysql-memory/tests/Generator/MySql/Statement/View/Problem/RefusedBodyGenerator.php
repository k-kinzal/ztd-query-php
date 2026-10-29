<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedBody values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody>
 */
final class RefusedBodyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $view Generated constructor operand
     * @param ErisGenerator<bool> $alter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Query|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery> $body Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     */
    public function __construct(ErisGenerator $view, ErisGenerator $alter, ErisGenerator $body, ?ErisGenerator $definer = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody {
            return new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody(view: $parts[0], alter: $parts[1], body: $parts[2], definer: $parts[3]);
        }, Generators::tuple(
            $view,
            $alter,
            $body,
            $definer ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody>}>
     */
    public static function executionForms(): iterable
    {
        yield 'View\\Problem\\RefusedBody' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedBodyGenerator(
            view: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'v' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            alter: Generators::constant(false),
            body: new \Tests\Generator\MySql\Statement\Routine\Program\DeferredSelectionGenerator(
                items: Generators::tuple(
                    new \Tests\Generator\Semantics\Statement\NodeGenerator([new \Tests\Generator\MySql\Statement\Routine\Program\DeferredProjectionGenerator(
                        expression: new \Tests\Generator\MySql\Statement\Expression\RowOperandGenerator([new \Tests\Generator\MySql\Statement\Expression\RowGenerator(
                            elements: Generators::tuple(
                                new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Variable\UserVariableGenerator(
                                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                                        value: Generators::map(static fn (int $suffix): string => 'x' . $suffix, Generators::choose(0, 8)),
                                        quote: Generators::constant(null)
                                    )
                                )]),
                                new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(
                                    text: Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 32))
                                )])
                            ),
                            keyword: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                        )]),
                        alias: Generators::constant(null),
                        mark: \Tests\Generator\MySql\Statement\Name\AliasMarkGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As])
                    )])
                ),
                from: Generators::constant(null),
                where: Generators::constant(null),
                options: Generators::constant([]),
                groupBy: Generators::constant(null),
                having: Generators::constant(null),
                orderBy: Generators::constant([]),
                limit: Generators::constant(null),
                windows: Generators::constant([]),
                procedure: Generators::constant(null),
                qualify: Generators::constant(null),
                into: Generators::constant(null)
            ),
            definer: Generators::constant(null)
        )];
    }

}
