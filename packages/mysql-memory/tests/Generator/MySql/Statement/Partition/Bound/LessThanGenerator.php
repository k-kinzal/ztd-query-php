<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Bound;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LessThan values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan>
 */
final class LessThanGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow|null> $row Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $row, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan(row: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $row,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
