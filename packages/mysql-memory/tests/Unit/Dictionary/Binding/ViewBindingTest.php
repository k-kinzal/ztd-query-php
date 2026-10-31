<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Binding;

use MySqlMemory\Dictionary\Binding\ViewBinding;
use MySqlMemory\Instance;
use MySqlMemory\Session\Statement\Catalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewBinding::class)]
#[Medium]
final class ViewBindingTest extends TestCase
{
    public function testCapturedValidityAndOutputIdentitySurviveLaterDictionaryChanges(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE VIEW v AS SELECT a FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $binding = Catalog::view($view, $session);
        $session->query('DROP TABLE t');

        self::assertTrue($binding->valid);
        self::assertSame($view, $binding->view);
        self::assertSame($view->declaration, $binding->declaration);
        self::assertSame([0], $binding->positions);
        self::assertFalse(Catalog::snapshot($session)->view($view)->valid);
    }

    public function testQueryBelongsToTheCurrentBindingAfterTheBaseTableChanges(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, b INT); CREATE VIEW v AS SELECT * FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $original = Catalog::view($view, $session);
        $session->query('DROP TABLE t; CREATE TABLE t(b INT, a INT)');
        $current = Catalog::view($view, $session);
        self::assertSame($view->definition, $original->query());
        self::assertNotSame($original->query(), $current->query());
        self::assertSame(['b', 'a'], array_map(static fn ($slot): ?string => $slot->name?->value, $current->query()->queryInformation()->shape->slots));
        self::assertSame([1, 0], $current->positions);
    }
}
