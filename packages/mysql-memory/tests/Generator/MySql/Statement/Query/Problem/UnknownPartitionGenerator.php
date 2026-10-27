<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnknownPartition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition>
 */
final class UnknownPartitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition> */
    use Composition;

    /**
     * @param ErisGenerator<string> $partition Generated constructor operand
     * @param ErisGenerator<string> $table Generated constructor operand
     */
    public function __construct(ErisGenerator $partition, ErisGenerator $table)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition(partition: $parts[0], table: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $partition,
            $table
        ));
    }
    /**
     * Composes a semantic condition for the executor's input-error path.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Query\\Problem\\UnknownPartition' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new UnknownPartitionGenerator(
            partition: Generators::constant('p9'),
            table: Generators::constant('t')
        )];
    }

}
