<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Window;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes WindowSpec values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec>
 */
final class WindowSpecGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $base Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem>>|null $partition Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem>>|null $order Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Call\Window\Frame|null>|null $frame Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $base = null, ?ErisGenerator $partition = null, ?ErisGenerator $order = null, ?ErisGenerator $frame = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(base: $parts[0], partition: $parts[1], order: $parts[2], frame: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple(
            $base ?? Generators::constant(null),
            $partition ?? Generators::constant([]),
            $order ?? Generators::constant([]),
            $frame ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
