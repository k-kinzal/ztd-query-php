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
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::oneOf(
            new BeginGenerator($release),
            new StartTransactionGenerator($release),
            new CommitGenerator($release),
            new RollbackGenerator($release),
            new SavepointGenerator($release),
            new RollbackToSavepointGenerator($release),
            new ReleaseSavepointGenerator($release),
            new \Tests\Generator\MySql\Statement\Utility\Set\SetTransactionGenerator($release),
            new Xa\XaStartGenerator($release),
            new Xa\XaEndGenerator($release),
            new Xa\XaPrepareGenerator($release),
            new Xa\XaCommitGenerator($release),
            new Xa\XaRollbackGenerator($release),
            new Xa\XaRecoverGenerator($release),
        );
    }

    /**
     * Couples each command to its outcome in a fresh session, before execution.
     * Missing savepoints and XIDs are explicit failures, while BEGIN, COMMIT,
     * ROLLBACK, SAVEPOINT and characteristic assignments must succeed.
     * Sources: https://dev.mysql.com/doc/refman/8.4/en/savepoint.html and
     * https://dev.mysql.com/doc/refman/8.4/en/xa-states.html.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function freshSession(GrammarRelease $release): ErisGenerator
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
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new self($release)));
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
