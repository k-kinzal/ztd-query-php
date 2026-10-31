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
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NameOperand::class)]
#[Medium]
final class NameOperandTest extends TestCase
{
    public function testDeriveScalarIsATextConstantAndNoColumnReference(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("ATTACH 'file.db' AS aux", []);
        $statement = $operation;

        self::assertInstanceOf(Attach::class, $statement);
        self::assertInstanceOf(NameOperand::class, $statement->schema);
        self::assertEquals(new Known(Storage::Text), $statement->schema->scalarInformation()->type);
        self::assertSame(Nullability::NotNull, $statement->schema->scalarInformation()->nullability);
        self::assertNull($statement->schema->scalarInformation()->resolution);
        self::assertSame([], $operation->diagnostics());
    }

    public function testRenderWritesTheNameAsAnIdentifier(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame('DETACH aux', $semantics->analyze('DETACH aux')->toString());
        self::assertSame('DETACH `my db`', $semantics->analyze('DETACH [my db]')->toString());
        self::assertSame('aux', (new NameOperand(new Name('aux')))->name->value);
    }
}
