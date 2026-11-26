<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Pragma::class)]
#[Medium]
final class PragmaTest extends TestCase
{
    public function testDeriveStatementRecordsNoOutputAndNoDiagnostic(): void
    {
        $operation = (new Inspection())->inspect('PRAGMA table_info(users)', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->declarations());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveStatementLeavesNumericOperandsForPragmaRules(): void
    {
        $operation = (new Inspection())->inspect('PRAGMA cache_size = -2000');
        $statement = $operation->statement;

        self::assertInstanceOf(Pragma::class, $statement);
        self::assertInstanceOf(PragmaNumber::class, $statement->value);
        self::assertSame(NumberSign::Minus, $statement->value->sign);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesBothValueNotationsWithAnEqualSign(): void
    {
        $semantics = new Inspection();

        self::assertSame('PRAGMA foreign_keys', $semantics->inspect('pragma foreign_keys')->toString());
        self::assertSame('PRAGMA main.cache_size = 4000', $semantics->inspect('PRAGMA main.cache_size(4000)')->toString());
        self::assertSame('PRAGMA main.cache_size = 4000', $semantics->inspect('PRAGMA main.cache_size == 4000')->toString());
    }

    public function testRenderKeepsTheKindOfEachValue(): void
    {
        $semantics = new Inspection();
        $keyword = $semantics->inspect('PRAGMA journal_mode = delete');
        $name = $semantics->inspect("PRAGMA journal_mode = 'delete'");
        $word = $semantics->inspect('PRAGMA journal_mode = wal');

        self::assertInstanceOf(Pragma::class, $keyword->statement);
        self::assertInstanceOf(Pragma::class, $name->statement);
        self::assertInstanceOf(Pragma::class, $word->statement);
        self::assertSame(PragmaKeyword::Delete, $keyword->statement->value);
        self::assertInstanceOf(Name::class, $name->statement->value);
        self::assertSame('delete', $name->statement->value->value);
        self::assertSame('PRAGMA journal_mode = DELETE', $keyword->toString());
        self::assertSame('PRAGMA journal_mode = `delete`', $name->toString());
        self::assertSame('PRAGMA journal_mode = wal', $word->toString());
    }

    public function testRenderWritesANewlyBuiltPragma(): void
    {
        $semantics = new Inspection();
        $operation = new InspectedStatement($semantics->context(), new Pragma(new QualifiedName(new Name('synchronous'), new Name('aux')), PragmaKeyword::On));

        self::assertSame('PRAGMA aux.synchronous = ON', $operation->toString());
    }
}
