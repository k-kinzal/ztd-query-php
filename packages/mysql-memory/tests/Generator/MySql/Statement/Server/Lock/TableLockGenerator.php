<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Lock;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TableLock values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock>
 */
final class TableLockGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $alias Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode> $mode Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\AliasMark>|null $mark Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     */
    public function __construct(ErisGenerator $table, ErisGenerator $alias, ErisGenerator $mode, ?ErisGenerator $mark = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $relationInformation = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock(table: $parts[0], alias: $parts[1], mode: $parts[2], mark: $parts[3], inputSource: $parts[4], relationInformation: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $table,
            $alias,
            $mode,
            $mark ?? Generators::constant(\SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As),
            $inputSource ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null)
        ));
    }
}
