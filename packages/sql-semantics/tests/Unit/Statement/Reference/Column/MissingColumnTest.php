<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(MissingColumn::class)]
#[Medium]
final class MissingColumnTest extends TestCase
{
    public function testMessageNamesTheUnqualifiedColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');

        $resolution = $semantics->analyze('SELECT b FROM t', [$table])->field('b')->resolution;

        self::assertInstanceOf(MissingColumn::class, $resolution);
        self::assertSame('Column b does not exist.', $resolution->message());
        self::assertSame('b', $resolution->name->value);
        self::assertNull($resolution->qualifier);
    }

    public function testMessageNamesTheQualifierTheUseWrote(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');

        $resolution = $semantics->analyze('SELECT t.b FROM t', [$table])->field('b')->resolution;

        self::assertInstanceOf(MissingColumn::class, $resolution);
        self::assertSame('Column t.b does not exist.', $resolution->message());
        self::assertSame('t', $resolution->qualifier?->name->value);
    }

    public function testMessageIsAlsoADiagnosticOfTheOperation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');

        $query = $semantics->analyze('SELECT b FROM t', [$table]);

        self::assertSame([$query->field('b')->resolution], $query->facts->diagnostics);
    }
}
