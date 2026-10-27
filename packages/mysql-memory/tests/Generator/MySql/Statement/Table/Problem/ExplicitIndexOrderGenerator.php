<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ExplicitIndexOrder values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder>
 */
final class ExplicitIndexOrderGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Direction> $direction Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm|null>|null $algorithm Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption>>|null $options Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\TableElement>>|null $elements Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $direction, ErisGenerator $table, ?ErisGenerator $algorithm = null, ?ErisGenerator $options = null, ?ErisGenerator $elements = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder(kind: $parts[0], direction: $parts[1], table: $parts[2], algorithm: $parts[3], options: $parts[4], elements: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $direction,
            $table,
            $algorithm ?? Generators::constant(null),
            $options ?? Generators::constant([]),
            $elements ?? Generators::constant([])
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Table\\Problem\\ExplicitIndexOrder' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new ExplicitIndexOrderGenerator(
            kind: \Tests\Generator\MySql\Statement\Table\Key\IndexKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText]),
            direction: \Tests\Generator\MySql\Statement\Query\DirectionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending]),
            table: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $suffix): string => 'p' . $suffix, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                ),
                schema: Generators::constant(null),
                catalog: Generators::constant(null)
            ),
            algorithm: Generators::constant(null),
            options: Generators::constant([]),
            elements: Generators::constant([])
        )];
    }

}
