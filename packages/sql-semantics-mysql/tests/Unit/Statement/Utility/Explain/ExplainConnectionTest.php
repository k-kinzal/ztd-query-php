<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(ExplainConnection::class)]
#[Medium]
final class ExplainConnectionTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $explain = (new Semantics('mysql-5.7.44'))->inspect('EXPLAIN PARTITIONS FOR CONNECTION 7');
        self::assertInstanceOf(ExplainConnection::class, $explain->statement);
        self::assertSame('partitions', $explain->field(3)->name?->value);
    }

    public function testRenderWritesTheConnection(): void
    {
        self::assertSame('EXPLAIN FORMAT = `json` FOR CONNECTION 7', (new Semantics())->inspect('explain format = json for connection 7')->toString());
    }

    public function testRefusesAModifierWithOptions(): void
    {
        $this->expectExceptionMessage('EXTENDED and PARTITIONS are written alone.');
        new ExplainConnection(new Numeral('1'), new Name('JSON'), false, ExplainModifier::Extended);
    }
}
