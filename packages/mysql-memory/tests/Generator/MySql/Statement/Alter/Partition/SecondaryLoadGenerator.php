<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SecondaryLoad values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\SecondaryLoad>
 */
final class SecondaryLoadGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\SecondaryLoad> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $load Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>>|null $partitions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $load, ?ErisGenerator $partitions = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Partition\SecondaryLoad {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\SecondaryLoad(load: $parts[0], partitions: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $load,
            $partitions ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
