<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Bound;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PartitionRow values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow>
 */
final class PartitionRowGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow(items: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $items,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
