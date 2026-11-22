<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Transaction\TransactionCommand;
use SqlSemantics\Platform\MySql\Ir\Transaction\TransactionLowering;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TransactionLowering::class)]
#[Medium]
final class TransactionLoweringTest extends TestCase
{
    public function testLowerDecodesTheCheckpointNameAndCommand(): void
    {
        $inspection = new Inspection();
        $operation = $inspection->inspect('ROLLBACK WORK TO `before work`');

        $value = (new TransactionLowering())->lower($operation->binding());

        self::assertSame(TransactionCommand::RollbackTo, $value->command);
        self::assertSame('before work', $value->name?->value);
    }
}
