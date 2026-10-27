<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Pragma::class)]
#[Medium]
final class PragmaTest extends TestCase
{
    public function testDeriveStatementRecordsNoOutputAndNoDiagnostic(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('PRAGMA table_info(users)', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->declarations());
        self::assertSame([], $operation->diagnostics());
    }

    public function testDeriveStatementDerivesTheNumberOfASignedValue(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('PRAGMA cache_size = -2000');
        $statement = $operation;

        self::assertInstanceOf(Pragma::class, $statement);
        self::assertInstanceOf(SignedNumber::class, $statement->value);
        self::assertSame(NumberSign::Minus, $statement->value->sign);
        self::assertEquals(new Known(Storage::Integer), $statement->value->number->scalarInformation()->type);
    }

    public function testRenderWritesBothValueNotationsWithAnEqualSign(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame('PRAGMA foreign_keys', $semantics->analyze('pragma foreign_keys')->toString());
        self::assertSame('PRAGMA main.cache_size = 4000', $semantics->analyze('PRAGMA main.cache_size(4000)')->toString());
        self::assertSame('PRAGMA main.cache_size = 4000', $semantics->analyze('PRAGMA main.cache_size == 4000')->toString());
    }

    public function testRenderKeepsTheKindOfEachValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $keyword = $semantics->analyze('PRAGMA journal_mode = delete');
        $name = $semantics->analyze("PRAGMA journal_mode = 'delete'");
        $word = $semantics->analyze('PRAGMA journal_mode = wal');

        self::assertInstanceOf(Pragma::class, $keyword);
        self::assertInstanceOf(Pragma::class, $name);
        self::assertInstanceOf(Pragma::class, $word);
        self::assertSame(PragmaKeyword::Delete, $keyword->value);
        self::assertInstanceOf(Name::class, $name->value);
        self::assertSame('delete', $name->value->value);
        self::assertSame('PRAGMA journal_mode = DELETE', $keyword->toString());
        self::assertSame('PRAGMA journal_mode = `delete`', $name->toString());
        self::assertSame('PRAGMA journal_mode = wal', $word->toString());
    }

    public function testRenderWritesANewlyBuiltPragma(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = Semantics::fromStatement($semantics->context(), new Pragma(new QualifiedName(new Name('synchronous'), new Name('aux')), PragmaKeyword::On));

        self::assertSame('PRAGMA aux.synchronous = ON', $operation->toString());
    }
}
