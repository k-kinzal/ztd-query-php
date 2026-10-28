<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AddPartition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition>
 */
final class AddPartitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\BinlogOption|null> $binlog Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition>> $definitions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $count Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $binlog, ErisGenerator $definitions, ?ErisGenerator $count = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition(binlog: $parts[0], definitions: $parts[1], count: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $binlog,
            $definitions,
            $count ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
