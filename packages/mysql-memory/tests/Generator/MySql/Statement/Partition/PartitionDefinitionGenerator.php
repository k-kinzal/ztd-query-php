<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PartitionDefinition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition>
 */
final class PartitionDefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionBound|null> $values Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption>> $options Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition>> $subpartitions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $boundarySource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $values, ErisGenerator $options, ErisGenerator $subpartitions, ?ErisGenerator $inputSource = null, ?ErisGenerator $boundarySource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition(name: $parts[0], values: $parts[1], options: $parts[2], subpartitions: $parts[3], inputSource: $parts[4], boundarySource: $parts[5]);
        }, Generators::tuple(
            $name,
            $values,
            $options,
            $subpartitions,
            $inputSource ?? Generators::constant(null),
            $boundarySource ?? Generators::constant(null)
        ));
    }
}
