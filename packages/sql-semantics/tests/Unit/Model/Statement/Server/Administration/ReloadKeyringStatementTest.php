<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Administration\ReloadKeyringStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReloadKeyringStatement::class)]
#[Medium]
final class ReloadKeyringStatementTest extends TestCase
{
    public function testWithOriginKeepsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER INSTANCE RELOAD KEYRING');
        self::assertInstanceOf(ReloadKeyringStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('ALTER INSTANCE RELOAD KEYRING', $copy->toString());
    }

    public function testRejectsALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER INSTANCE ROTATE INNODB MASTER KEY');
        $this->expectException(InvalidStructure::class);
        new ReloadKeyringStatement($statement->origin);
    }
}
