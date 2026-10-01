<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Statement;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Statement\ValuesRow::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ValuesRowTest extends TestCase
{
    public function testToStringPreservesValueOrder(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (foo, label) VALUES (1, \'x\')');
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, $statement);
        self::assertSame("(1, 'x')", $statement->rows[0]->toString());
    }
}
