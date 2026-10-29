<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes MissingPartitions values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions>
 */
final class MissingPartitionsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind> $kind Generated constructor operand
     */
    public function __construct(ErisGenerator $kind)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions(kind: $parts[0]);
        }, Generators::tuple(
            $kind
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Partition\\Problem\\MissingPartitions' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new MissingPartitionsGenerator(
            kind: \Tests\Generator\MySql\Statement\Partition\Method\PartitionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range])
        )];
    }

}
