<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A three-valued membership test over resolved native signed integer operands.
 *
 * @visibility public
 * @example Checking an exact scalar against two alternatives
 *     $test = new \SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership(new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('2'), [new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('1'), new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('2')]);
 *     count($test->alternatives) // => 2
 */
final class IntegerMembership
{
    use Snapshot;

    /**
     * @var non-empty-list<ColumnReference|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull> The stored alternatives
     */
    public readonly array $alternatives;

    /**
     * Copies ordered alternatives and restricts their roles to pure native signed operands.
     * @param array<array-key, mixed> $alternatives The right-hand list in order
     */
    public function __construct(
        public readonly ColumnReference|IntegerLiteral|NullLiteral|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull $subject,
        array $alternatives,
        public readonly bool $negated = false,
    ) {
        Check::input(array_is_list($alternatives) && $alternatives !== [] && count($alternatives) <= 8190, 'Signed membership requires an ordered list of one to 8190 alternatives.');
        $terms = [];
        foreach ($alternatives as $alternative) {
            Check::input(
                $alternative instanceof ColumnReference || $alternative instanceof IntegerLiteral || $alternative instanceof NullLiteral
                    || $alternative instanceof WideIntegerColumnReference || $alternative instanceof WideIntegerLiteral || $alternative instanceof WideIntegerNull,
                'A membership alternative must be a pure native signed operand.',
            );
            $terms[] = $alternative;
        }
        $this->alternatives = $terms;
    }
}
