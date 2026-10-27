<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Column;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DefaultLiteral values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral>
 */
final class DefaultLiteralGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal|\SqlSemantics\Platform\MySql\Statement\Call\ClockCall|\SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $value, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral(value: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $value,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
