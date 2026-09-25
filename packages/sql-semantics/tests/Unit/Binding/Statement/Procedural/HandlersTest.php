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
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($key), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($key))));
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

    #[TestWith(['handler t open', 'HANDLER `t` OPEN'])]
    #[TestWith(['handler t read first where a > 1', 'HANDLER `t` READ FIRST WHERE (`a` > 1)'])]
    #[TestWith(['handler t read k next where a > 1 limit 3', 'HANDLER `t` READ `k` NEXT WHERE (`a` > 1) LIMIT 3'])]
    #[TestWith(['handler t read k = (1, 2) where b < 5', 'HANDLER `t` READ `k` = (1, 2) WHERE (`b` < 5)'])]
    #[TestWith(['handler t read k prev', 'HANDLER `t` READ `k` PREV'])]
    #[TestWith(['handler t close', 'HANDLER `t` CLOSE'])]
    public function testBindReadsLowerCaseHandlerForms(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT, KEY k (a, b))')))->bind($sql)));
    }

    public function testKeyBindsEveryKeyValue(): void
    {
        $node = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('HANDLER t READ k = (1, 2)');
        $key = Handlers::key($node, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
        self::assertSame(['1', '2'], array_map(static fn ($value): ?string => $value->spelling(), $key));
    }

    public function testReadBindsTheConditionAgainstTheHandlerTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT, KEY k (a, b))')))->bind('HANDLER t READ FIRST WHERE a > 1');
        self::assertInstanceOf(Statement\ReadHandlerStatement::class, $statement);
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT, KEY k (a, b))'), new \SqlSemantics\Ast\Identifiers(Dialect::MySql), ''));
        $read = Handlers::read($statement->origin, $statement->source, $statement->handler, 3, $context);
        self::assertSame('HANDLER `t` READ FIRST WHERE (`a` > 1)', (new \SqlSemantics\SimpleSerializer())->serialize($read));
    }
}
