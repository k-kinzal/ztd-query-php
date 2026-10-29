<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Bound;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PartitionMaximum values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum>
 */
final class PartitionMaximumGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum(inputSource: $parts[0]);
        }, Generators::tuple(
            $inputSource ?? Generators::constant(null)
        ));
    }
}
