<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Instance;
use MySqlMemory\Session\Access\StoredViewSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredViewSyntax::class)]
#[Small]
final class StoredViewSyntaxTest extends TestCase
{
    public function testErrorDependsOnReachedDefinitionsInsteadOfInputText(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE VIEW v AS WITH a AS (SELECT 1 AS x), b AS (TABLE a) TABLE a');
        $session->text = 'unrelated invalid input';
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $view = $schema->views['v'];
        $error = (new StoredViewSyntax())->error($view);
        self::assertSame(1064, $error?->getCode());
        self::assertStringContainsString("near 'select `a`.`x` AS `x` from `a`'", $error->getMessage());
    }

    public function testErrorLeavesEntirelyUnusedDefinitionsAndUsedFinalDefinitionsReadable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE VIEW v AS WITH a AS (SELECT 1 AS x) SELECT 2 AS x');
        $session->query('CREATE VIEW w AS WITH a AS (SELECT 1 AS x), b AS (TABLE a) TABLE b');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $views = $schema->views;
        self::assertNull((new StoredViewSyntax())->error($views['v']));
        self::assertNull((new StoredViewSyntax())->error($views['w']));
    }
    public function testErrorRetainsTheRemainderAfterANestedBody(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE VIEW v AS SELECT * FROM (WITH a AS (SELECT 1 AS x), b AS (TABLE a) TABLE a) q');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $error = (new StoredViewSyntax())->error($schema->views['v']);
        self::assertNotNull($error);
        self::assertStringContainsString("near 'select `a`.`x` AS `x` from `a`) `q`'", $error->getMessage());
    }

}
