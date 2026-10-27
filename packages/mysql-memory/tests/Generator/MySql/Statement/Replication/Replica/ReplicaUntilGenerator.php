<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Replica;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ReplicaUntil values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil>
 */
final class ReplicaUntilGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint|null> $point Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $gtids Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption>>|null $positions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $point, ?ErisGenerator $gtids = null, ?ErisGenerator $positions = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil(point: $parts[0], gtids: $parts[1], positions: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $point,
            $gtids ?? Generators::constant(null),
            $positions ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
