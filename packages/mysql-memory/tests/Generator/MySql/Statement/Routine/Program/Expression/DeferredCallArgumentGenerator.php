<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredCallArgument values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument>
 */
final class DeferredCallArgumentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $alias Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AliasMark>|null $mark Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $expression, ?ErisGenerator $alias = null, ?ErisGenerator $mark = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument(expression: $parts[0], alias: $parts[1], mark: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $expression,
            $alias ?? Generators::constant(null),
            $mark ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
