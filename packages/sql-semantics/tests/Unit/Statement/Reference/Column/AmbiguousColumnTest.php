<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(AmbiguousColumn::class)]
#[Medium]
final class AmbiguousColumnTest extends TestCase
{
    public function testMessageAndCandidatesOfANameTwoInputsShare(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $declarations = [$semantics->analyze('CREATE TABLE t (a INTEGER)'), $semantics->analyze('CREATE TABLE u (a TEXT)')];
        $query = $semantics->analyze('SELECT a FROM t, u', $declarations);

        $resolution = $query->field('a')->resolution;

        self::assertInstanceOf(AmbiguousColumn::class, $resolution);
        self::assertSame('Column a is ambiguous.', $resolution->message());
        self::assertSame('a', $resolution->name->value);
        self::assertCount(2, $resolution->candidates);
        self::assertSame($declarations[0]->declarations()[0]->columns[0], $resolution->candidates[0]->declaration());
        self::assertSame($declarations[1]->declarations()[0]->columns[0], $resolution->candidates[1]->declaration());
        self::assertSame([$resolution], $query->facts->diagnostics);
    }

    public function testMessageIsNotReachedWithFewerThanTwoCandidates(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $resolution = $semantics->analyze('SELECT a FROM t', [$table])->field('a')->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $this->expectExceptionMessage('An ambiguous column has at least two candidates.');

        new AmbiguousColumn(new Name('a'), [$resolution]);
    }
}
