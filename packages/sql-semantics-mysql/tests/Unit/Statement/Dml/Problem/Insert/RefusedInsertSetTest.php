<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet;

#[CoversClass(RefusedInsertSet::class)]
#[Medium]
final class RefusedInsertSetTest extends TestCase
{
    public function testMessageRetainsTheCompleteStandaloneWrite(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('INSERT INTO d.t SET a=1, b=ROW(2,3) ON DUPLICATE KEY UPDATE c=4');
            self::fail('A non-scalar SET assignment cannot become an ordinary statement.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(RefusedInsertSet::class, $failure->problem);
            self::assertSame('d', $failure->problem->write->into->table->name->schema?->value);
            self::assertCount(2, $failure->problem->write->assignments);
            self::assertCount(1, $failure->problem->write->onDuplicate);
            self::assertSame('Operand should contain 1 column(s), not 2.', $failure->problem->message());
        }
    }
}
