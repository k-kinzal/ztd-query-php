<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem\GeneratedColumnWrite;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem\WriteKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GeneratedColumnWrite::class)]
#[Medium]
final class GeneratedColumnWriteTest extends TestCase
{
    public function testMessageNamesTheColumnAfterTheWrite(): void
    {
        $problem = new GeneratedColumnWrite(WriteKind::Insert, new Name('Total "sum"'));

        self::assertSame('cannot INSERT into generated column "Total "sum""', $problem->message());
        self::assertSame(WriteKind::Insert, $problem->write);
    }

    public function testMessageNamesTheColumnAsDeclared(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a, Total AS (a * 2) STORED)');
        $problem = $semantics->analyze('UPDATE t SET total = 1', [$table])->facts->diagnostics[0] ?? null;

        self::assertInstanceOf(GeneratedColumnWrite::class, $problem);
        self::assertSame(WriteKind::Update, $problem->write);
        self::assertSame($table->declarations()[0]->columns[1]->name, $problem->column);
        self::assertSame('cannot UPDATE generated column "Total"', $problem->message());
    }
}
