<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family\Defaults;

use MySqlMemory\Evaluation\Compile\Family\Defaults\ViewColumn;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewColumn::class)]
#[Medium]
final class ViewColumnTest extends TestCase
{
    public function testColumnFollowsReboundViewOutputsWithoutInstallingThemInTheDictionary(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT DEFAULT 7); INSERT t VALUES (3); CREATE VIEW v AS SELECT a AS x FROM t');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $before = $view->declaration;
        $session->query('ALTER TABLE t MODIFY a INT DEFAULT 9');

        $result = $session->query('SELECT DEFAULT(x) FROM v')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['9']], $result->rows);
        self::assertSame($before, $view->declaration);
    }
}
