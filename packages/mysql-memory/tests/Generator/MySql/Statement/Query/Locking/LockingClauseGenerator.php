<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Locking;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LockingClause values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause>
 */
final class LockingClauseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength> $strength Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\QualifiedName>>|null $tables Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction|null>|null $action Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>>|null $wildcards Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $strength, ?ErisGenerator $tables = null, ?ErisGenerator $action = null, ?ErisGenerator $wildcards = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause(strength: $parts[0], tables: $parts[1], action: $parts[2], wildcards: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple(
            $strength,
            $tables ?? Generators::constant([]),
            $action ?? Generators::constant(null),
            $wildcards ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
