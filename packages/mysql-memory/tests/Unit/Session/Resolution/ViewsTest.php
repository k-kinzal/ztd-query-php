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
    public function testCaptureReusesOnlyUnchangedDeclarationAndSettingsInputs(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE VIEW v AS SELECT a FROM t');
        $dictionary = $session->instance->dictionary;
        $before = Views::capture($dictionary, $session->settings());
        $session->query('INSERT t VALUES (1)');
        self::assertSame($before, Views::capture($dictionary, $session->settings()));
        $session->query('ALTER TABLE t MODIFY a VARCHAR(12)');
        $changed = Views::capture($dictionary, $session->settings());
        self::assertNotSame($before, $changed);
        $session->query('SET div_precision_increment=6');
        self::assertNotSame($changed, Views::capture($dictionary, $session->settings()));
    }

    public function testCaptureInvalidatesReplacedViewsAndEmptySchemas(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE VIEW v AS SELECT 1 AS a');
        $dictionary = $session->instance->dictionary;
        $before = Views::capture($dictionary, $session->settings());
        $session->query('CREATE OR REPLACE VIEW v AS SELECT 2 AS b');
        $changed = Views::capture($dictionary, $session->settings());
        self::assertNotSame($before, $changed);
        $session->query('CREATE DATABASE empty_schema');
        self::assertNotSame($changed, Views::capture($dictionary, $session->settings()));
    }

    public function testCapturePublishesValuesWithoutRetainingTheTraversal(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE VIEW v AS SELECT 1 AS a');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);

        $snapshot = Views::capture($session->instance->dictionary, $session->settings());

        self::assertTrue($snapshot->view($view)->valid);
        self::assertContains($view->declaration, $snapshot->tables());
    }

    public function testBindRebindsTheRetainedStructure(): void
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

        $binding = Views::bind($view, $session->instance->dictionary->declarations(), $session->settings());

        self::assertSame($definition, $view->query);
        self::assertSame($before, $view->operation);
        self::assertNotSame($before, $binding->operation);
        self::assertSame([], $binding->operation->statementInformation()->diagnostics);
    }

    public function testBindRetainsMaterializedColumnTypes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE VIEW v AS SELECT 1 n, a FROM t LIMIT 1');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['v'];
        $session->query('DROP TABLE t; CREATE TABLE t (a VARCHAR(12))');
        $session->text = 'This is not SQL';

        $binding = Views::bind($view, $session->instance->dictionary->declarations(), $session->settings());

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $binding->declaration->columns[0]->type);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long, $binding->declaration->columns[0]->type->field);
    }

    public function testBindRecordsInvalidViewsWithoutPlanningThem(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE VIEW v AS SELECT a FROM t; DROP TABLE t');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['v'];

        $binding = Views::bind($view, $session->instance->dictionary->declarations(), $session->settings());

        self::assertFalse($binding->valid);
    }
}
