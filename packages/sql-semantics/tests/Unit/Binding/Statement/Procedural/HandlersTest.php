<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\Handlers;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Cursor\Handler as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Handlers::class)]
#[Medium]
final class HandlersTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindClassifiesEveryHandlerFormAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT, KEY k(a))'));
        self::assertInstanceOf(Statement\OpenHandlerStatement::class, $binder->bind('HANDLER t OPEN'));
        self::assertInstanceOf(Statement\CloseHandlerStatement::class, $binder->bind('HANDLER t CLOSE'));
        self::assertInstanceOf(Statement\ReadHandlerStatement::class, $binder->bind('HANDLER t READ FIRST'));
        self::assertInstanceOf(Statement\ReadHandlerIndexStatement::class, $binder->bind('HANDLER t READ k FIRST'));
        $key = $binder->bind('HANDLER t READ k >= (1) WHERE a < 5 LIMIT 2');
        self::assertInstanceOf(Statement\ReadHandlerKeyStatement::class, $key);
        self::assertSame($key->toString(), $binder->bind($key->toString())->toString());
    }

    public function testBindKeepsAnAliasHandlerAsADiagnosedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('HANDLER h CLOSE', strict: false);
        self::assertInstanceOf(Statement\CloseHandlerStatement::class, $statement);
        self::assertFalse($statement->handler->declaration->resolved);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
    }

    public function testReadTreatsAnIndexNamedLikeAScanStepAsAnIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY first(a))')))->bind('HANDLER t READ first NEXT');
        self::assertInstanceOf(Statement\ReadHandlerIndexStatement::class, $statement);
        self::assertSame('first', $statement->index);
    }

    public function testKeyRejectsDefault(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefaultContext->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k(a))')))->bind('HANDLER t READ k = (DEFAULT)');
    }
}
