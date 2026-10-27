<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DropPartition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition>
 */
final class DropPartitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions> $partitions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $partitions, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition(partitions: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $partitions,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
