<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SemanticInsert::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SemanticInsertTest extends TestCase
{
    public function testReadDistinguishesInsertionSources(): void
    {
        $analysis = new \SqlSemantics\Facade\Semantics(Dialect::Sqlite);
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, $analysis->analyze('INSERT INTO bar (foo) VALUES (1)'));
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertSelect::class, $analysis->analyze('INSERT INTO bar (foo) SELECT 1'));
    }
}
