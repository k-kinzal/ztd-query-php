<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedCache;
use MySqlMemory\Command\Prepared\PreparedStatement;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Parse\Preparation\Alternatives;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class PreparedViewBindingTest extends TestCase
{
    public function testCatalogInvalidationRebindsAViewInTheOriginalDatabaseOnlyOnce(): void
    {
        $session = (new Instance(databases: ['original', 'later']))->connect(database: 'original');
        $session->query('CREATE TABLE t(a INT); INSERT t VALUES(7); CREATE VIEW v AS SELECT a FROM t');
        $view = $session->instance->dictionary->schema('original')?->views['v'];
        self::assertNotNull($view);
        $declaration = $view->declaration;
        $sql = 'SELECT * FROM v';
        $operation = $session->analyze($sql, true);
        $cache = PreparedCache::capture($operation, $session, (new Alternatives())->read($sql, $session));
        $parameters = ParameterBindings::capture($operation);
        $first = $cache->definition;
        $session->query('ALTER TABLE t MODIFY a VARCHAR(12); USE later; CREATE VIEW v AS SELECT 99 AS a');

        $cache->refresh($session, $parameters, []);

        self::assertInstanceOf(PreparedStatement::class, $cache->definition);
        self::assertNotSame($first, $cache->definition);
        $rebound = $cache->definition;
        $reply = $rebound->execute($session, 'not SQL', []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['7']], $reply->rows);
        self::assertSame('later', $session->variables->database);
        self::assertSame($declaration, $view->declaration);
        self::assertFalse($cache->changed($session));

        $cache->refresh($session, $parameters, []);

        self::assertSame($rebound, $cache->definition);
    }
}
