<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Locking\LockStrength;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LockStrength::class)]
#[Medium]
final class LockStrengthTest extends TestCase
{
    public function testRepresentsEveryRowLockMode(): void
    {
        self::assertSame(['UPDATE', 'NO KEY UPDATE', 'SHARE', 'KEY SHARE'], array_column(LockStrength::cases(), 'value'));
    }

    #[TestWith([LockStrength::Update])]
    #[TestWith([LockStrength::NoKeyUpdate])]
    #[TestWith([LockStrength::Share])]
    #[TestWith([LockStrength::KeyShare])]
    public function testBindsTheStrengthFromItsSpelling(LockStrength $strength): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t FOR ' . $strength->value);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($strength, $statement->locks[0]->strength);
        self::assertSame('SELECT "id" AS "id" FROM "public"."t" FOR ' . $strength->value, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
