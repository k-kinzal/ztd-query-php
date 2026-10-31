<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Resolution;

use MySqlMemory\Instance;
use MySqlMemory\Session\Resolution\ViewBindings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewBindings::class)]
#[Medium]
final class ViewBindingsTest extends TestCase
{
    public function testResolveVisitsDependenciesBeforeTheRequestedViewAndRetainsTheDefinition(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT); CREATE VIEW v AS SELECT a FROM t; CREATE VIEW w AS SELECT a FROM v');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['w'];
        $before = $view->declaration;
        $session->query('ALTER TABLE t MODIFY a VARCHAR(8)');
        $bindings = new ViewBindings($session->instance->dictionary, $session->settings());

        $bound = $bindings->resolve($view);

        self::assertTrue($bound->valid);
        self::assertSame($bound, $bindings->resolve($view));
        self::assertSame($before, $view->declaration);
        self::assertNotSame($before, $bound->declaration);
        self::assertCount(2, $bindings->views);
        self::assertContains($bound->declaration, $bindings->tables);
    }
}
