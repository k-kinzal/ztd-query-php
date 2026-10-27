<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\View\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RefusedQuery values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery>
 */
final class RefusedQueryGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $view Generated constructor operand
     * @param ErisGenerator<bool> $alter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery|\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection> $query Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account|null>|null $definer Generated constructor operand
     */
    public function __construct(ErisGenerator $view, ErisGenerator $alter, ErisGenerator $query, ?ErisGenerator $definer = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery {
            return new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery(view: $parts[0], alter: $parts[1], query: $parts[2], definer: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $view,
            $alter,
            $query,
            $definer ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery>}>
     */
    public static function executionForms(): iterable
    {
        yield 'View\\Problem\\RefusedQuery' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new RefusedQueryGenerator(
            view: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('v'),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            alter: Generators::constant(true),
            query: new \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedQueryGenerator(
                query: new \Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredQueryGenerator(
                    query: new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
                        options: Generators::constant([]),
                        items: \Tests\Generator\Operands::tuple(
                            new \Tests\Generator\MySql\Statement\Query\StarGenerator()
                        ),
                        from: Generators::constant(null),
                        where: Generators::constant(null),
                        groupBy: Generators::constant(null),
                        having: Generators::constant(null),
                        windows: Generators::constant([]),
                        qualify: Generators::constant(null),
                        orderBy: Generators::constant([]),
                        limit: Generators::constant(null),
                        procedure: Generators::constant(null),
                        locking: Generators::constant([]),
                        into: Generators::constant(null),
                        intoPosition: Generators::constant(null),
                        late: Generators::constant(null),
                        hints: Generators::constant([])
                    )
                )
            ),
            definer: Generators::constant(null)
        )];
    }

}
