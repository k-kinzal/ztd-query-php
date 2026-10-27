<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Attach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(Attach::class)]
#[Medium]
final class AttachTest extends TestCase
{
    public function testDeriveStatementDerivesEveryOperandWhereNoRelationIsVisible(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("ATTACH 'a' || name AS aux KEY 'k'", []);
        $statement = $operation;

        self::assertInstanceOf(Attach::class, $statement);
        self::assertNotNull($statement->key);
        self::assertTrue($statement->file->hasBinding());
        self::assertTrue($statement->key->hasBinding());
        self::assertCount(1, $operation->diagnostics());
        self::assertInstanceOf(MissingColumn::class, $operation->diagnostics()[0]);
    }

    public function testDeriveStatementProvidesNoDeclaration(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("ATTACH 'file.db' AS aux");

        self::assertSame([], $operation->declarations());
        self::assertNull($operation->shape());
    }

    public function testRenderDropsTheOptionalKeywordAndKeepsTheKey(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze("attach database 'file.db' as aux key 'secret'");

        self::assertInstanceOf(Attach::class, $operation);
        self::assertInstanceOf(NameOperand::class, $operation->schema);
        self::assertSame("ATTACH 'file.db' AS aux KEY 'secret'", $operation->toString());
    }
}
