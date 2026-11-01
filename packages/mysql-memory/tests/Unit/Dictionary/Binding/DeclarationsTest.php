<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Binding;

use MySqlMemory\Dictionary\Binding\Declarations;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Declarations::class)]
#[Medium]
final class DeclarationsTest extends TestCase
{
    public function testTablesKeepsAnalysisAndExecutionNamespacesIndependent(): void
    {
        $instance = new Instance(databases: ['d']);
        $first = $instance->connect(database: 'd');
        $second = $instance->connect(database: 'd');
        $first->query('CREATE TABLE t (base_value INT); CREATE TEMPORARY TABLE t (owner_value INT); INSERT t VALUES (11)');
        $second->query("CREATE TEMPORARY TABLE t (peer_value VARCHAR(12)); INSERT t VALUES ('second')");
        $permanent = $instance->dictionary->declarations();
        $firstTables = \MySqlMemory\Session\Statement\Catalog::declarations($first);
        $readFirst = $first->analyze('SELECT owner_value FROM t');
        $readSecond = $second->analyze('SELECT peer_value FROM t');

        self::assertSame($permanent, $instance->dictionary->declarations());
        self::assertSame($firstTables, \MySqlMemory\Session\Statement\Catalog::declarations($first));
        $answerFirst = (new Execution($first))->statement($readFirst);
        $answerSecond = (new Execution($second))->statement($readSecond);
        self::assertInstanceOf(ResultSet::class, $answerFirst);
        self::assertInstanceOf(ResultSet::class, $answerSecond);
        self::assertSame([['11']], $answerFirst->rows);
        self::assertSame([['second']], $answerSecond->rows);
        self::assertSame('base_value', $instance->dictionary->table('d', 't')?->definition->columns[0]->name);
    }

    public function testViewRebindsNestedDefinitionsWithoutChangingStoredIdentities(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT); CREATE VIEW inner_view AS SELECT a FROM t; CREATE VIEW outer_view AS SELECT a FROM inner_view');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $inner = $schema->views['inner_view'];
        $outer = $schema->views['outer_view'];
        $original = [$inner->operation, $inner->declaration, $outer->operation, $outer->declaration];
        $session->query("DROP TABLE t; CREATE TABLE t (a VARCHAR(16)); INSERT t VALUES ('persistent'); CREATE TEMPORARY TABLE t (a INT); INSERT t VALUES (9)");

        $snapshot = \MySqlMemory\Session\Statement\Catalog::snapshot($session);
        $binding = $snapshot->view($outer);
        $session->analyze('SELECT * FROM outer_view');

        self::assertTrue($binding->valid);
        self::assertInstanceOf(Domain::class, $binding->declaration->columns[0]->type);
        self::assertSame(Kind::String, $binding->declaration->columns[0]->type->kind);
        self::assertSame($original, [$inner->operation, $inner->declaration, $outer->operation, $outer->declaration]);
        self::assertSame([$inner, $outer], [$schema->views['inner_view'], $schema->views['outer_view']]);
        $result = $session->query('SELECT * FROM outer_view')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['persistent']], $result->rows);
    }

    public function testViewPropagatesMissingDependenciesAndRecoversInANewSnapshot(): void
    {
        $instance = new Instance(databases: ['d']);
        $session = $instance->connect(database: 'd');
        $administrator = $instance->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT); CREATE VIEW v AS SELECT a FROM t; CREATE VIEW w AS SELECT a FROM v; CREATE TEMPORARY TABLE t (a INT)');
        $schema = $instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['w'];
        $administrator->query('DROP TABLE t');
        $invalid = \MySqlMemory\Session\Statement\Catalog::snapshot($session);

        self::assertFalse($invalid->view($view)->valid);
        $administrator->query('CREATE TABLE t (a INT)');
        self::assertFalse($invalid->view($view)->valid);
        self::assertTrue(\MySqlMemory\Session\Statement\Catalog::snapshot($session)->view($view)->valid);
        self::assertSame($view, $schema->views['w']);
    }

    public function testTablesAllowsATemporaryTableToShadowAViewOnlyForItsOwner(): void
    {
        $instance = new Instance(databases: ['d']);
        $owner = $instance->connect(database: 'd');
        $other = $instance->connect(database: 'd');
        $owner->query('CREATE VIEW v AS SELECT 1 AS base_value; CREATE TEMPORARY TABLE v (temporary_value INT); INSERT v VALUES (2)');
        $owner->analyze('SELECT temporary_value FROM v');
        $other->analyze('SELECT base_value FROM v');
        $temporary = $owner->query('SELECT * FROM v')[0];
        $view = $other->query('SELECT * FROM v')[0];

        self::assertInstanceOf(ResultSet::class, $temporary);
        self::assertInstanceOf(ResultSet::class, $view);
        self::assertSame([['2']], $temporary->rows);
        self::assertSame([['1']], $view->rows);
        $owner->query('DROP TEMPORARY TABLE v');
        self::assertSame(\MySqlMemory\Session\Statement\Catalog::declarations($other), \MySqlMemory\Session\Statement\Catalog::declarations($owner));
    }
}
