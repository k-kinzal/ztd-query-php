<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\GeneratedColumnFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\GeneratedColumnProblem;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GeneratedColumnProblem::class)]
#[Medium]
final class GeneratedColumnProblemTest extends TestCase
{
    public function testMessageIsThatOfTheFlaw(): void
    {
        $problem = new GeneratedColumnProblem(GeneratedColumnFlaw::WithDefault, new Name('b'));

        self::assertSame('A generated column cannot have a DEFAULT value.', $problem->message());
        self::assertSame('b', $problem->column?->value);
    }

    public function testMessageIsReportedForEachWrongUseOfAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $flaw = static fn (string $sql): array => array_map(static fn (object $diagnostic): array => $diagnostic instanceof GeneratedColumnProblem ? [$diagnostic->flaw->name, $diagnostic->column?->value] : [$diagnostic::class], $semantics->analyze($sql, [])->facts->diagnostics);

        self::assertSame([['WithDefault', 'b']], $flaw('CREATE TABLE t (a, b DEFAULT 1 AS (a))'));
        self::assertSame([['WithDefault', 'b']], $flaw('CREATE TABLE t (a, b GENERATED ALWAYS AS (a) STORED DEFAULT (1))'));
        self::assertSame([['InPrimaryKey', 'b']], $flaw('CREATE TABLE t (a, b AS (a) PRIMARY KEY)'));
        self::assertSame([['InPrimaryKey', 'b']], $flaw('CREATE TABLE t (a, b AS (a), PRIMARY KEY (b))'));
        self::assertSame([['NoPlainColumn', null]], $flaw('CREATE TABLE t (a AS (1), b AS (a))'));
        self::assertSame([], $flaw('CREATE TABLE t (a, b AS (a) VIRTUAL, c AS (b) STORED)'));
    }
}
