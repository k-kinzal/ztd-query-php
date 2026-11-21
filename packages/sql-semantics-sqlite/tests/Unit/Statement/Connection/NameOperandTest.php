<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Connection\Attach;
use SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

#[CoversClass(NameOperand::class)]
#[Medium]
final class NameOperandTest extends TestCase
{
    public function testDeriveScalarIsATextConstantAndNoColumnReference(): void
    {
        $operation = (new Semantics())->analyze("ATTACH 'file.db' AS aux", []);
        $statement = $operation->statement;

        self::assertInstanceOf(Attach::class, $statement);
        self::assertInstanceOf(NameOperand::class, $statement->schema);
        self::assertEquals(new Known(Storage::Text), $operation->facts->scalar($statement->schema)->type);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($statement->schema)->nullability);
        self::assertNull($operation->facts->scalar($statement->schema)->resolution);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheNameAsAnIdentifier(): void
    {
        $semantics = new Semantics();

        self::assertSame('DETACH aux', $semantics->analyze('DETACH aux')->toString());
        self::assertSame('DETACH `my db`', $semantics->analyze('DETACH [my db]')->toString());
        self::assertSame('aux', (new NameOperand(new Name('aux')))->name->value);
    }
}
