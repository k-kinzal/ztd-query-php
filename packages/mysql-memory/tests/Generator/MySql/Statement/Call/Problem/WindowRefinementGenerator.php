<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WindowRefinement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement>
 */
final class WindowRefinementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec> $window Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition>>|null $ancestors Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $name Generated constructor operand
     */
    public function __construct(ErisGenerator $window, ?ErisGenerator $ancestors = null, ?ErisGenerator $name = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement(window: $parts[0], ancestors: $parts[1], name: $parts[2]);
        }, Generators::tuple(
            $window,
            $ancestors ?? Generators::constant([]),
            $name ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement>}>
     */
    public static function executionForms(): iterable
    {
        $conditions = Generators::bind(Generators::tuple(new WindowRefinementKindGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), Generators::choose(0, 32)), static function (array $parts): self {
            [$kind, $name, $number] = $parts;
            $order = new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $number));
            $parent = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(
                order: $kind === \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind::Ordering ? [$order] : [],
                frame: $kind === \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind::Frame ? new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow)) : null,
            );
            $window = new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                Generators::constant($name),
                Generators::constant($kind === \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind::Partition ? [$order] : []),
                Generators::constant($kind === \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind::Ordering ? [$order] : []),
            );
            $ancestors = Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\Clause\WindowDefinitionGenerator(Generators::constant($name), Generators::constant($parent)));
            return new self($window, $ancestors);
        });
        yield 'Call/Problem/WindowRefinement' => [\SqlSemantics\Contract\GrammarRelease::MySql847, $conditions];
    }

}
