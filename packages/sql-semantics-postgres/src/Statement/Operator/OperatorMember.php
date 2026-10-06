<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `OPERATOR strategy operator [(types)] [FOR SEARCH | FOR ORDER BY family]`: an operator of an operator class or family.
 *
 * Without operand types the operator's operands are the class's data type.
 * The obsolete trailing RECHECK is ignored by the server and not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createopclass.html.
 *
 * @visibility public
 * @example Reading the strategy number
 *     $member = new \SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorMember(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'), new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('<')));
 *     $member->strategy->digits // => '1'
 */
final class OperatorMember implements OperatorClassItem
{
    use Snapshot;

    /**
     * @param IntegerConstant $strategy The strategy number
     * @param OperatorName|OperatorSignature $operator The operator, with or without operand types, written without OPERATOR(...)
     * @param MemberPurpose|null $purpose What the operator is for, if written
     */
    public function __construct(public readonly IntegerConstant $strategy, public readonly OperatorName|OperatorSignature $operator, public readonly ?MemberPurpose $purpose = null)
    {
        Check::input(!$operator instanceof OperatorName || !$operator->explicit, 'An operator class names an operator without OPERATOR(...).');
    }

    /**
     * Derives the operand types.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->operator->deriveClause($derivation, $environment);
    }

    /**
     * Writes OPERATOR, the strategy, the operator and the purpose.
     */
    public function render(Output $out): void
    {
        $out->keyword('OPERATOR')->node($this->strategy)->node($this->operator)->node($this->purpose);
    }
}
