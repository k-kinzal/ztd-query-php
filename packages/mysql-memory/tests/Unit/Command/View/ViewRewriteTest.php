<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ViewRewrite;
use MySqlMemory\Command\View\ViewWriteCommand;
use MySqlMemory\Command\View\ViewWrites;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Operation;

#[CoversClass(ViewRewrite::class)]
#[Small]
final class ViewRewriteTest extends TestCase
{
    public function testReplacementLeavesAnUnrelatedOccurrenceAlone(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); CREATE VIEW v AS SELECT a AS x FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $operation = $session->analyze('UPDATE v SET x = 2');
        self::assertInstanceOf(Operation::class, $operation);
        $target = ViewWriteCommand::viewTarget($operation);
        self::assertNotNull($target);
        $select = $session->analyze('SELECT 1');
        self::assertInstanceOf(Operation::class, $select);
        $unrelated = $select->statement;

        self::assertNull((new ViewRewrite())->replacement($unrelated, $operation, $writes, $target, new \SqlSemantics\Construction\Reconstruction(), []));
    }

    public function testInsertionExpandsTheImplicitViewColumnList(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT,b INT); CREATE VIEW v AS SELECT b AS y,a AS x FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $operation = $session->analyze('INSERT INTO v VALUES (2,1)');
        self::assertInstanceOf(Operation::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $operation->statement);
        $target = ViewWriteCommand::viewTarget($operation);
        self::assertNotNull($target);
        $into = (new ViewRewrite())->insertion($operation->statement->into, $writes, $target, false);
        self::assertNotNull($into->columns);
        self::assertSame(['b', 'a'], array_map(static fn ($column) => $column instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse ? $column->name->value : null, $into->columns->columns));
    }

    public function testOperationInsertRefusesAComputedColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW e AS SELECT a, b + 1 AS bb FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['e'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $operation = $session->analyze('INSERT INTO e VALUES (5, 6)');
        self::assertInstanceOf(Operation::class, $operation);
        $target = ViewWriteCommand::viewTarget($operation);
        self::assertNotNull($target);

        $this->expectExceptionCode(1348);
        $this->expectExceptionMessage("Column 'bb' is not updatable");

        (new ViewRewrite())->operation($operation, $writes, $target)->toString();
    }

    public function testOperationInsertWritesTheBaseColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW w AS SELECT a AS x, b FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['w'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $operation = $session->analyze('INSERT INTO w (x) VALUES (1)');
        self::assertInstanceOf(Operation::class, $operation);
        $target = ViewWriteCommand::viewTarget($operation);
        self::assertNotNull($target);

        self::assertSame('INSERT INTO d.t (a) VALUES (1)', (new ViewRewrite())->operation($operation, $writes, $target)->toString());
    }

    public function testOperationChangeAddsTheConditionOfTheView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW w AS SELECT a AS x, b FROM t q WHERE q.a > 0');
        $view = $session->instance->dictionary->schema('d')?->views['w'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $operation = $session->analyze('UPDATE w SET x = x * 100 WHERE b < 3');
        self::assertInstanceOf(Operation::class, $operation);
        $target = ViewWriteCommand::viewTarget($operation);
        self::assertNotNull($target);

        self::assertSame('UPDATE d.t AS q SET a = q.a * 100 WHERE (q.b < 3) AND (q.a > 0)', (new ViewRewrite())->operation($operation, $writes, $target)->toString());
    }

    public function testOperationRetargetWritesTheStatementAgainstTheBaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW w AS SELECT a AS x, b FROM t q WHERE q.a > 0');
        $view = $session->instance->dictionary->schema('d')?->views['w'];
        self::assertNotNull($view);
        $writes = ViewWrites::of($view, $session);
        self::assertNotNull($writes);
        $operation = $session->analyze('UPDATE w SET x = b ORDER BY x LIMIT 2');
        self::assertInstanceOf(Operation::class, $operation);
        $target = ViewWriteCommand::viewTarget($operation);
        self::assertNotNull($target);

        self::assertSame('UPDATE d.t AS q SET a = q.b WHERE q.a > 0 ORDER BY q.a LIMIT 2', (new ViewRewrite())->operation($operation, $writes, $target)->toString());
    }
}
