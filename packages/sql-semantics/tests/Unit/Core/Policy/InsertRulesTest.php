<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Policy\InsertRules::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InsertRulesTest extends TestCase
{
    public function testReadDistinguishesRowsFromAQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, $semantics->analyze('INSERT INTO bar (foo) VALUES (1)'));
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertSelect::class, $semantics->analyze('INSERT INTO bar (foo) SELECT 1'));
    }
}
