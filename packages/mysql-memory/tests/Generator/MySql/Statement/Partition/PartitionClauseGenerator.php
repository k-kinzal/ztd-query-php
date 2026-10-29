<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PartitionClause values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause>
 */
final class PartitionClauseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionMethod> $method Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null> $partitions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Subpartitioning|null> $subpartitioning Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition>> $definitions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $method, ErisGenerator $partitions, ErisGenerator $subpartitioning, ErisGenerator $definitions, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause(method: $parts[0], partitions: $parts[1], subpartitioning: $parts[2], definitions: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple(
            $method,
            $partitions,
            $subpartitioning,
            $definitions,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
