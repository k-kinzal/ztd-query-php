<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Partition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TablespaceCommand values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand>
 */
final class TablespaceCommandGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $import Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection|null>|null $partitions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $import, ?ErisGenerator $partitions = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand(import: $parts[0], partitions: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $import,
            $partitions ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
