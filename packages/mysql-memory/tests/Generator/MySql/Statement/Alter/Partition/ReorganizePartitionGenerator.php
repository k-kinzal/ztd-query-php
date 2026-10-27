<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ReorganizePartition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition>
 */
final class ReorganizePartitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $local Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions|null>|null $partitions Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition>>|null $definitions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $local, ?ErisGenerator $partitions = null, ?ErisGenerator $definitions = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition(local: $parts[0], partitions: $parts[1], definitions: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $local,
            $partitions ?? Generators::constant(null),
            $definitions ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
