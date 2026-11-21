<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Attach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand;

#[CoversClass(Attach::class)]
#[Medium]
final class AttachTest extends TestCase
{
    public function testDeriveStatementDerivesEveryOperandWhereNoRelationIsVisible(): void
    {
        $operation = (new Semantics())->inspect("ATTACH 'a' || name AS aux KEY 'k'", []);
        $statement = $operation->statement;

        self::assertInstanceOf(Attach::class, $statement);
        self::assertNotNull($statement->key);
        self::assertTrue($operation->facts->covers($statement->file));
        self::assertTrue($operation->facts->covers($statement->key));
        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveStatementProvidesNoDeclaration(): void
    {
        $operation = (new Semantics())->inspect("ATTACH 'file.db' AS aux");

        self::assertSame([], $operation->declarations());
        self::assertNull($operation->shape());
    }

    public function testRenderDropsTheOptionalKeywordAndKeepsTheKey(): void
    {
        $semantics = new Semantics();
        $operation = $semantics->inspect("attach database 'file.db' as aux key 'secret'");

        self::assertInstanceOf(Attach::class, $operation->statement);
        self::assertInstanceOf(NameOperand::class, $operation->statement->schema);
        self::assertSame("ATTACH 'file.db' AS aux KEY 'secret'", $operation->toString());
    }
}
