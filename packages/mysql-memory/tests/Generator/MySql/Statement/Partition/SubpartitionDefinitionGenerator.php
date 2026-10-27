<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SubpartitionDefinition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition>
 */
final class SubpartitionDefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $options, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition(name: $parts[0], options: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $options,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
