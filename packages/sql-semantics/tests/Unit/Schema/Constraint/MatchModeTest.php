<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\Schema\Constraint\MatchMode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MatchMode::class)]
#[Medium]
final class MatchModeTest extends TestCase
{
    public function testRepresentsEveryMatchClause(): void
    {
        self::assertSame(['simple', 'full', 'partial'], array_column(MatchMode::cases(), 'value'));
    }

    public function testClassifiesTheMatchClauseAndDefaultsToSimple(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER PRIMARY KEY); CREATE TABLE c(a INTEGER REFERENCES p(id) MATCH FULL, b INTEGER REFERENCES p(id))')->tables[1];
        self::assertInstanceOf(ForeignKey::class, $table->constraints[0]);
        self::assertInstanceOf(ForeignKey::class, $table->constraints[1]);
        self::assertSame(MatchMode::Full, $table->constraints[0]->match);
        self::assertSame(MatchMode::Simple, $table->constraints[1]->match);
    }
}
