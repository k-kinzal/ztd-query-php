<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\UtilityNoise;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(UtilityNoise::class)]
#[Medium]
final class UtilityNoiseTest extends TestCase
{
    public function testPositionsListsOnlyTheOptionalWordsAndSeparators(): void
    {
        self::assertSame(
            [
                'opt_transaction: WORK' => [0],
                'opt_transaction: TRANSACTION' => [0],
                'TransactionStmt: RELEASE SAVEPOINT ColId' => [1],
                'TransactionStmt: ROLLBACK opt_transaction TO SAVEPOINT ColId' => [3],
                'transaction_mode_list: transaction_mode_list , transaction_mode_item' => [1],
                'generic_set: var_name TO var_list' => [1],
                'generic_set: var_name = var_list' => [1],
                'generic_set: var_name TO DEFAULT' => [1],
                'generic_set: var_name = DEFAULT' => [1],
                'VariableSetStmt: SET SESSION set_rest' => [1],
            ],
            UtilityNoise::positions(),
        );
    }

    public function testPositionsLetTheStatementsRoundTripWithoutTheWords(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['ROLLBACK TO s', 'BEGIN READ ONLY DEFERRABLE', 'SET a TO DEFAULT', 'SET a TO 1'],
            [$semantics->inspect('ROLLBACK TRANSACTION TO SAVEPOINT s')->toString(), $semantics->inspect('BEGIN WORK READ ONLY, DEFERRABLE')->toString(), $semantics->inspect('SET a = DEFAULT')->toString(), $semantics->inspect('SET SESSION a = 1')->toString()],
        );
    }
}
