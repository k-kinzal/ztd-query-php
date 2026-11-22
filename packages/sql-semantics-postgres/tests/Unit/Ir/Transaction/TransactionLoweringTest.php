<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Transaction\TransactionCommand;
use SqlSemantics\Platform\PostgreSql\Ir\Transaction\TransactionLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(TransactionLowering::class)]
#[Medium]
final class TransactionLoweringTest extends TestCase
{
    public function testLowerDecodesTheCheckpointNameAndCommand(): void
    {
        $inspection = new Inspection();
        $operation = $inspection->inspect('ROLLBACK WORK TO "before work"');

        $value = (new TransactionLowering())->lower($operation->binding());

        self::assertSame(TransactionCommand::RollbackTo, $value->command);
        self::assertSame('before work', $value->name?->value);
    }

    public function testLowerRejectsChaining(): void
    {
        $inspection = new Inspection();
        $operation = $inspection->inspect('COMMIT AND CHAIN');
        $this->expectException(InvalidConstruction::class);
        $this->expectExceptionMessage('cannot chain');

        (new TransactionLowering())->lower($operation->binding());
    }
}
