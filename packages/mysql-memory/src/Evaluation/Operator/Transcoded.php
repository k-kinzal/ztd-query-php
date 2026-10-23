<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * A string known when the statement is resolved that an operation reads in another character set: read once for the statement.
 *
 * The server converts such an argument into the character set the operation settles on while it
 * resolves the statement, so the argument is evaluated once, before any row is read and even when
 * none is, and warns once; a string the operation reads in its own character set, a number and a
 * temporal value are read for each row (verified on a live 8.4 server). An argument that fails
 * when it is resolved is read the first time a row needs it instead.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collation-coercibility.html.
 *
 * @visibility MySqlMemory
 */
final class Transcoded implements Evaluable
{
    /**
     * @param Evaluable $operand The string read
     */
    public function __construct(public readonly Evaluable $operand)
    {
    }

    /**
     * Answers the domain of the string.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->operand->domain();
    }

    /**
     * Reads the string the first time it is needed, and answers the same value for every later row of the statement.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $kept = $frame->context->kept;
        if (!isset($kept[$this])) {
            $kept[$this] = [$this->operand->evaluate($frame)];
        }

        return $kept[$this][0];
    }

    /**
     * Resolves a compiled operand an operation reads in a collation: a non-binary string known when the statement is resolved, in another non-binary character set, is read now, once.
     *
     * @param bool $resolved Whether the operand is known when the statement is resolved
     * @param \MySqlMemory\Evaluation\Context $context The statement being resolved
     */
    public static function of(Evaluable $operand, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation $collation, bool $resolved, \MySqlMemory\Evaluation\Context $context): Evaluable
    {
        $domain = $operand->domain();
        $string = $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String;
        if (!$resolved || !$string || $domain->collation->bytes() || $collation->bytes() || $domain->collation->charset === $collation->charset || $operand instanceof \MySqlMemory\Evaluation\Leaf\Constant) {
            return $operand;
        }
        try {
            return new \MySqlMemory\Evaluation\Leaf\Constant($domain, $operand->evaluate(new Frame($context)));
        } catch (\MySqlMemory\Error\SqlError) {
            return new self($operand);
        }
    }
}
