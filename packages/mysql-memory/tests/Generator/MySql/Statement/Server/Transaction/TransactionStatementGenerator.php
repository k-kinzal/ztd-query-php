<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement;

/**
 * Composes TransactionStatement operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<TransactionStatement>
 */
final class TransactionStatementGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TransactionStatement> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar and enclosing SQL context before generating operands.
     * BEGIN has no stored-program statement form; START TRANSACTION does.
     */
    public function __construct(GrammarRelease $release, bool $stored = false)
    {
        $forms = [
            StartTransactionGenerator::operands($release),
            CommitGenerator::operands($release),
            RollbackGenerator::operands($release),
            SavepointGenerator::operands($release),
            RollbackToSavepointGenerator::operands($release),
            ReleaseSavepointGenerator::operands($release),
            \Tests\Generator\MySql\Statement\Utility\Set\SetTransactionGenerator::operands($release),
            Xa\XaStartGenerator::operands($release),
            Xa\XaEndGenerator::operands($release),
            Xa\XaPrepareGenerator::operands($release),
            Xa\XaCommitGenerator::operands($release),
            Xa\XaRollbackGenerator::operands($release),
            Xa\XaRecoverGenerator::operands($release),
        ];
        if (!$stored) {
            $forms[] = BeginGenerator::operands($release);
        }
        $this->delegate = Generators::oneOf(...$forms);
    }

    /**
     * Couples each command to its outcome in a fresh session, before execution.
     * Missing savepoints and XIDs are explicit failures, while BEGIN, COMMIT,
     * ROLLBACK, SAVEPOINT and characteristic assignments must succeed.
     * Sources: https://dev.mysql.com/doc/refman/8.4/en/savepoint.html and
     * https://dev.mysql.com/doc/refman/8.4/en/xa-states.html.
     *
     * @param ErisGenerator<TransactionStatement> $statements The concrete transaction result to explore
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function freshSession(GrammarRelease $release, ErisGenerator $statements): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): array {
            $code = match (true) {
                $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint,
                $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint => 1305,
                $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart => $statement->option === null ? 0 : 1398,
                $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd => $statement->option === null ? 1399 : 1398,
                $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare => 1399,
                $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit,
                $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback => 1397,
                default => 0,
            };
            return [$statement, $code];
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $statements));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<TransactionStatement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<TransactionStatement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
