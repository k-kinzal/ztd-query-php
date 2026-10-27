<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PartitionOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption>
 */
final class PartitionOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $number Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $text Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $name = null, ?ErisGenerator $number = null, ?ErisGenerator $text = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption(kind: $parts[0], name: $parts[1], number: $parts[2], text: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $kind,
            $name ?? Generators::constant(null),
            $number ?? Generators::constant(null),
            $text ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
