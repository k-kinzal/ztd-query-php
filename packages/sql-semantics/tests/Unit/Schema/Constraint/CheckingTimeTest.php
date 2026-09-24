<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\Schema\Constraint\PrimaryKey;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CheckingTime::class)]
#[Medium]
final class CheckingTimeTest extends TestCase
{
    public function testRepresentsEveryDeferralPolicy(): void
    {
        self::assertSame(['not-deferrable', 'deferrable-immediate', 'deferrable-deferred'], array_column(CheckingTime::cases(), 'value'));
    }

    public function testClassifiesDeferrableDeclarations(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, CONSTRAINT pk PRIMARY KEY (id) DEFERRABLE); CREATE TABLE c(pid INTEGER REFERENCES p(id) DEFERRABLE INITIALLY DEFERRED, qid INTEGER REFERENCES p(id))');
        $primary = $schema->tables[0]->constraints[0];
        $deferred = $schema->tables[1]->constraints[0];
        $immediate = $schema->tables[1]->constraints[1];
        self::assertInstanceOf(PrimaryKey::class, $primary);
        self::assertInstanceOf(ForeignKey::class, $deferred);
        self::assertInstanceOf(ForeignKey::class, $immediate);
        self::assertSame(CheckingTime::DeferrableImmediate, $primary->checking);
        self::assertSame(CheckingTime::DeferrableDeferred, $deferred->checking);
        self::assertSame(CheckingTime::Immediate, $immediate->checking);
    }
}
