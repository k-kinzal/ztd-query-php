<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MaintainPartitions values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions>
 */
final class MaintainPartitionsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintenanceKind> $kind Generated constructor operand
     * @param ErisGenerator<bool> $local Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection> $partitions Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\CheckOption>|list<\SqlSemantics\Platform\MySql\Statement\Server\RepairOption>>|null $options Generated constructor operand
     * @param ErisGenerator<bool>|null $ignoredLocal Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $local, ErisGenerator $partitions, ?ErisGenerator $options = null, ?ErisGenerator $ignoredLocal = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions(kind: $parts[0], local: $parts[1], partitions: $parts[2], options: $parts[3], ignoredLocal: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $local,
            $partitions,
            $options ?? Generators::constant([]),
            $ignoredLocal ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
