<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\KeyCache;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CachedTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable>
 */
final class CachedTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection|null>|null $partitions Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex>|null>|null $indexes Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\IndexWord|null>|null $keyword Generated constructor operand
     */
    public function __construct(ErisGenerator $table, ?ErisGenerator $partitions = null, ?ErisGenerator $indexes = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $keyword = null)
    {
        $this->delegate = \Tests\Generator\Operands::bind(\Tests\Generator\Operands::tuple(
            $table,
            $partitions ?? Generators::constant(null),
            $indexes ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null)
        ), static function (array $parts) use ($keyword): ErisGenerator {
            return Generators::map(static fn (?\SqlSemantics\Platform\MySql\Statement\Table\IndexWord $selected): \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable => new \SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable(table: $parts[0], partitions: $parts[1], indexes: $parts[2], inputSource: $parts[3], relationInformation: $parts[4], keyword: $selected), $keyword ?? ($parts[2] !== null ? new \Tests\Generator\MySql\Statement\Table\IndexWordGenerator() : Generators::constant(null)));
        });
    }
}
