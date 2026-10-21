<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\View;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(View::class)]
#[Small]
final class ViewTest extends TestCase
{
    public function testQuerySqlRendersTheRetainedQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE VIEW v AS SELECT 1 AS x');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        self::assertSame('SELECT 1 AS x', $schema->views['v']->querySql());
    }

    public function testRenamedRetainsTheDefinitionAndBindingState(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a DATE); CREATE ALGORITHM=MERGE VIEW v AS SELECT a FROM t');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $original = $schema->views['v'];
        $renamed = $original->renamed('w');
        self::assertSame('w', $renamed->name);
        self::assertSame('w', $renamed->declaration->name->name->value);
        self::assertSame($original->declaration->columns, $renamed->declaration->columns);
        self::assertSame($original->created, $renamed->created);
        self::assertSame($original->definition, $renamed->definition);
        self::assertSame($original->tables, $renamed->tables);
    }

    public function testCreateWritesTheAttributesAroundTheQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE DEFINER=CURRENT_USER SQL SECURITY INVOKER VIEW v AS SELECT 1 AS x WITH CHECK OPTION');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['v'];

        self::assertSame('CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY INVOKER VIEW `v` AS select 1 AS `x` WITH CASCADED CHECK OPTION', $view->create('`v`', 'select 1 AS `x`'));
        self::assertSame(['SELECT 1 AS x', $view->operation], [$view->querySql(), $view->created]);
    }
}
