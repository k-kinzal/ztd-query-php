<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnsupportedWindowing values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing>
 */
final class UnsupportedWindowingGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit> $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate|\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat|\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate|\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec|null>|null $expression Generated constructor operand
     */
    public function __construct(ErisGenerator $limit, ?ErisGenerator $expression = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing(limit: $parts[0], expression: $parts[1]);
        }, Generators::tuple(
            $limit,
            $expression ?? Generators::constant(null)
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Call\\Problem\\UnsupportedWindowing' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnsupportedWindowingGenerator(
            limit: WindowingLimitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit::GroupsUnit]),
            expression: new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(
                base: Generators::constant(null),
                partition: Generators::constant([]),
                order: Generators::constant([]),
                frame: new \Tests\Generator\MySql\Statement\Call\Window\FrameGenerator(
                    unit: \Tests\Generator\MySql\Statement\Call\Window\FrameUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Groups]),
                    start: new \Tests\Generator\MySql\Statement\Call\Window\FrameBoundGenerator(
                        kind: \Tests\Generator\MySql\Statement\Call\Window\FrameBoundKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow]),
                        offset: Generators::constant(null),
                        unit: Generators::constant(null)
                    ),
                    end: Generators::constant(null),
                    exclusion: Generators::constant(null)
                )
            )
        )];
    }

}
