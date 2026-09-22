<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\IdentityReset;
use SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IdentityReset::class)]
#[Medium]
final class IdentityResetTest extends TestCase
{
    #[TestWith(['CONTINUE IDENTITY', IdentityReset::Continue])]
    #[TestWith(['RESTART IDENTITY', IdentityReset::Restart])]
    public function testClassifiesTheSequenceRequest(string $sql, IdentityReset $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('TRUNCATE t ' . $sql);
        self::assertInstanceOf(TruncateRelationsStatement::class, $statement);
        self::assertSame($expected, $statement->identities);
        self::assertSame('TRUNCATE TABLE "public"."t" ' . $sql . ' RESTRICT', $statement->toString());
    }
}
