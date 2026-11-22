<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(MissingColumn::class)]
#[Medium]
final class MissingColumnTest extends TestCase
{
    public function testMessageNamesTheUnqualifiedColumn(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INTEGER)');

        $resolution = $semantics->diagnose('SELECT b FROM t', [$table])->field('b')->resolution;

        self::assertInstanceOf(MissingColumn::class, $resolution);
        self::assertSame('Column b does not exist.', $resolution->message());
        self::assertSame('b', $resolution->name->value);
        self::assertNull($resolution->qualifier);
    }

    public function testMessageNamesTheQualifierTheUseWrote(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INTEGER)');

        $resolution = $semantics->diagnose('SELECT t.b FROM t', [$table])->field('b')->resolution;

        self::assertInstanceOf(MissingColumn::class, $resolution);
        self::assertSame('Column t.b does not exist.', $resolution->message());
        self::assertSame('t', $resolution->qualifier?->name->value);
    }

    public function testMessageIsAlsoADiagnosticOfTheOperation(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INTEGER)');

        $query = $semantics->diagnose('SELECT b FROM t', [$table]);

        self::assertSame([$query->field('b')->resolution], $query->facts->diagnostics);
    }
}
