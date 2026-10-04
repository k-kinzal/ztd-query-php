<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;

#[CoversClass(StorageOption::class)]
#[Medium]
final class StorageOptionTest extends TestCase
{
    public function testKeywordNamesTheOption(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE TABLESPACE ts ADD DATAFILE 'f' ENGINE ndb NO_WAIT");
        self::assertInstanceOf(CreateTablespace::class, $create->statement);

        self::assertSame(['ENGINE', 'WAIT'], [$create->statement->options[0]->keyword(), $create->statement->options[1]->keyword()]);
    }
}
