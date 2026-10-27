<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NamedPartitions values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions>
 */
final class NamedPartitionsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $names Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $names, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions(names: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $names,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
