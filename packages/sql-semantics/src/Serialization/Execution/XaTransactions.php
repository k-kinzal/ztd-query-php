<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Execution;

use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Transaction\Xa as Statement;
use SqlSemantics\Model\Transaction\Xa\TransactionId;
use SqlSemantics\Serialization\Expressions;

/**
 * Serializes XA operands and policies independently of the original statement text.
 * @visibility SqlSemantics
 */
final class XaTransactions
{
    /**
     * Writes exactly the operands allowed by the concrete XA operation.
     */
    public static function write(Statement\XaStartStatement|Statement\XaEndStatement|Statement\XaPrepareStatement|Statement\XaCommitStatement|Statement\XaRollbackStatement|Statement\XaRecoverStatement $statement): Tree
    {
        if ($statement instanceof Statement\XaRecoverStatement) {
            return new Tree('xa-recover', [Build::keyword('XA RECOVER'), Build::keyword($statement->encoding->value)]);
        }
        $policy = $statement instanceof Statement\XaStartStatement || $statement instanceof Statement\XaEndStatement || $statement instanceof Statement\XaCommitStatement ? [Build::keyword($statement->mode->value)] : [];
        return new Tree('xa-transaction', [Build::keyword($statement->kind->value), self::identifier($statement->transactionId), ...$policy]);
    }

    /**
     * Serializes literal identifier components without changing their quoting or precision.
     */
    public static function identifier(TransactionId $identifier): Tree
    {
        $parts = [Expressions::write($identifier->global)];
        if ($identifier->branch !== null) {
            $parts[] = Expressions::write($identifier->branch->qualifier);
            if ($identifier->branch->format !== null) {
                $parts[] = new Tree('format-identifier', [new Atom('number', $identifier->branch->format->spelling)]);
            }
        }
        return Build::separated($parts);
    }
}
