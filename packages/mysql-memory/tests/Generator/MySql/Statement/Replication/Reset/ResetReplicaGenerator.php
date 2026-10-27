<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Reset;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ResetReplica values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica>
 */
final class ResetReplicaGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<bool>|null $all Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $channel Generated constructor operand
     * @param ErisGenerator<bool>|null $synonym Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $terminology, ?ErisGenerator $all = null, ?ErisGenerator $channel = null, ?ErisGenerator $synonym = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(terminology: $parts[0], all: $parts[1], channel: $parts[2], synonym: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $terminology,
            $all ?? Generators::constant(false),
            $channel ?? Generators::constant(null),
            $synonym ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
