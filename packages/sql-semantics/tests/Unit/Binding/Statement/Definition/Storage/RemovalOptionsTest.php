<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\DropTablespaceStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Storage\RemovalOptions::class)]
#[Medium]
final class RemovalOptionsTest extends TestCase
{
    public function testEngineDecodesTextIdentifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP TABLESPACE store ENGINE 'N\\DB'");
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        self::assertSame('NDB', $statement->engine);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testEngineRejectsRepeatedDeclarations(string $version): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('DROP TABLESPACE store ENGINE NDB ENGINE NDB');
    }

    public function testWaitingUsesTheLastRequestInTheModernGrammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE store NO_WAIT NO_WAIT WAIT');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        self::assertSame(CompletionWait::Wait, $statement->waiting);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testWaitingRejectsRepeatedNoWaitInTheLegacyGrammar(string $version): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('DROP TABLESPACE store NO_WAIT NO_WAIT');
    }

    public function testWaitingAllowsLegacyNoWaitAfterAnExplicitWait(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('DROP TABLESPACE store NO_WAIT WAIT NO_WAIT');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        self::assertSame(CompletionWait::NoWait, $statement->waiting);
    }
}
