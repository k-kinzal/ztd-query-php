<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Resolution;

use MySqlMemory\Instance;
use MySqlMemory\Session\Resolution\Views;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Views::class)]
#[Small]
final class ViewsTest extends TestCase
{
    public function testRefreshRebindsTheRetainedStructure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE VIEW v AS SELECT a FROM t');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['v'];
        $definition = $view->definition;
        $before = $view->operation;
        $session->query('DROP TABLE t; CREATE TABLE t (a VARCHAR(12))');
        $session->text = 'This is not SQL';

        Views::refresh($view, $session->instance->dictionary, $session->settings());

        self::assertSame($definition, $view->query);
        self::assertNotSame($before, $view->operation);
        self::assertSame([], $view->operation->facts->diagnostics);
    }

    public function testRefreshRetainsMaterializedColumnTypes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE VIEW v AS SELECT 1 n, a FROM t LIMIT 1');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['v'];
        $session->query('DROP TABLE t; CREATE TABLE t (a VARCHAR(12))');
        $session->text = 'This is not SQL';

        Views::refresh($view, $session->instance->dictionary, $session->settings());

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $view->declaration->columns[0]->type);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long, $view->declaration->columns[0]->type->field);
    }

    public function testRefreshAllRecordsInvalidViewsWithoutPlanningThem(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE VIEW v AS SELECT a FROM t; DROP TABLE t');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['v'];

        Views::refreshAll($session->instance->dictionary, $session->settings());

        self::assertFalse($view->valid);
    }
}
