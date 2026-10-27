<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Statement;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Statement\Catalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Catalog::class)]
#[Medium]
final class CatalogTest extends TestCase
{
    public function testSnapshotCapturesPermanentDeclarationsWithoutSessionShadows(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TEMPORARY TABLE t(b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertContains($table->definition->declaration, Catalog::snapshot($session)->tables());
        self::assertNotContains($table->definition->declaration, Catalog::declarations($session));
    }

    public function testDeclarationsKeepsEachSessionsTemporaryNamespaceExplicit(): void
    {
        $instance = new Instance(databases: ['d']);
        $owner = $instance->connect(database: 'd');
        $other = $instance->connect(database: 'd');
        $owner->query('CREATE TEMPORARY TABLE t(a INT)');
        $table = $owner->temporaries->table('d', 't');
        self::assertNotNull($table);

        self::assertContains($table->definition->declaration, Catalog::declarations($owner));
        self::assertNotContains($table->definition->declaration, Catalog::declarations($other));
        self::assertNotContains($table->definition->declaration, $instance->dictionary->declarations());
    }

    public function testViewRejectsAnInvalidSnapshotWithoutChangingTheDefinition(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT); CREATE VIEW v AS SELECT a FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $before = $view->created;
        Catalog::view($view, $session);
        $session->query('DROP TABLE t');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1356);
        try {
            Catalog::view($view, $session);
        } finally {
            self::assertSame($before, $view->created);
            self::assertSame($view, $session->instance->dictionary->schema('d')?->views['v']);
        }
    }
}
