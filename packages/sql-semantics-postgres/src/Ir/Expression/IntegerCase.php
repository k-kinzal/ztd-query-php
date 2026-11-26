<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An ordered PostgreSQL searched CASE with a resolved int4 result.
 *
 * @visibility public
 * @example Constructing a choice with no written ELSE
 *     $arm = new \SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseArm(new \SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral(false), new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('4'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase([$arm]))->otherwise // => null
 */
final class IntegerCase
{
    use Snapshot;

    /**
     * @var non-empty-list<IntegerCaseArm> The decisions in selection order
     */
    public readonly array $arms;

    /**
     * Checks and copies the nonempty list of typed decisions.
     * @param list<IntegerCaseArm> $arms The branches in selection order
     */
    public function __construct(
        array $arms,
        public readonly Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|self|null $otherwise = null,
    ) {
        Check::input(count($arms) <= 32, 'An int4 CASE can have at most 32 arms.');
        $this->arms = Check::listOf($arms, IntegerCaseArm::class, 'An int4 CASE needs a nonempty list of typed arms.', 1);
    }
}
