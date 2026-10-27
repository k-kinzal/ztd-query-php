<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CoalescePartition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\CoalescePartition>
 */
final class CoalescePartitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\CoalescePartition> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $local Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $count Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $local, ErisGenerator $count, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Partition\CoalescePartition {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\CoalescePartition(local: $parts[0], count: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $local,
            $count,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
