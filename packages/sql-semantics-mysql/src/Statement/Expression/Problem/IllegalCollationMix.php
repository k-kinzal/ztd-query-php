<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An operation on strings whose collations hold equally strongly and cannot be reconciled.
 *
 * The server names both operands of a two-operand operation (`ER_CANT_AGGREGATE_2COLLATIONS`,
 * error 1267), all three of a three-operand operation (`ER_CANT_AGGREGATE_3COLLATIONS`, error
 * 1270), and none of a longer one (`ER_CANT_AGGREGATE_NCOLLATIONS`, error 1271).
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_cant_aggregate_2collations.
 *
 * @visibility public
 * @example Comparing two explicit collations
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT 'a' COLLATE utf8mb4_bin = 'b' COLLATE utf8mb4_general_ci");
 *     $query->facts->diagnostics[0]->message() // => "Illegal mix of collations (utf8mb4_bin,EXPLICIT) and (utf8mb4_general_ci,EXPLICIT) for operation '='"
 */
final class IllegalCollationMix implements Diagnostic
{
    use Snapshot;

    /**
     * @param list<array{string, Coercibility}> $operands The collation and coercibility of each operand
     * @param string $operation The operation as the server names it
     */
    public function __construct(public readonly array $operands, public readonly string $operation)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        $named = array_map(static fn (array $operand): string => sprintf('(%s,%s)', $operand[0], self::level($operand[1])), $this->operands);

        return match (count($named)) {
            2 => sprintf("Illegal mix of collations %s and %s for operation '%s'", $named[0], $named[1], $this->operation),
            3 => sprintf("Illegal mix of collations %s for operation '%s'", implode(', ', $named), $this->operation),
            default => sprintf("Illegal mix of collations for operation '%s'", $this->operation),
        };
    }

    /**
     * Names a coercibility as the server does in the message.
     */
    public static function level(Coercibility $level): string
    {
        return match ($level) {
            Coercibility::Explicit => 'EXPLICIT',
            Coercibility::None => 'NONE',
            Coercibility::Implicit => 'IMPLICIT',
            Coercibility::SystemConstant => 'SYSCONST',
            Coercibility::Coercible => 'COERCIBLE',
            Coercibility::Numeric => 'NUMERIC',
            Coercibility::Ignorable => 'IGNORABLE',
        };
    }
}
