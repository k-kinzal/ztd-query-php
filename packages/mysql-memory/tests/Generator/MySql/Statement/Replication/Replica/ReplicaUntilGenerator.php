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
        }, Generators::tuple(
            $point,
            $gtids ?? Generators::constant(null),
            $positions ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates an incomplete coordinate or a coordinate mixed with a GTID/gap condition.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil>
     */
    public static function conditions(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $positions = Generators::tuple(new \Tests\Generator\MySql\Statement\Replication\Source\SourceOptionGenerator(
            Generators::constant((new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->source($release)),
            \Tests\Generator\MySql\Statement\Replication\Source\SourceOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::LogPosition, \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::RelayLogPosition]),
            new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(4, 10000))),
        ));
        return Generators::oneOf(...[
            new self(Generators::constant(null), positions: $positions),
            new self(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::AfterGaps), positions: $positions),
            new self(UntilPointGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::BeforeGtids, \SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::AfterGtids]), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $value): string => '3E11FA47-71CA-11E1-9E33-C80AA9429562:1-' . $value, Generators::choose(1, 1000))), $positions),
        ]);
    }

}
