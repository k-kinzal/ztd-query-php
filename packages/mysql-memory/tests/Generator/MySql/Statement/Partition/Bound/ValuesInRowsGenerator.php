<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Bound;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ValuesInRows values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows>
 */
final class ValuesInRowsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow>> $rows Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $rows, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows(rows: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $rows,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
