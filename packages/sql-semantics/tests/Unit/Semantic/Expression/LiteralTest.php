<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\Literal::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class LiteralTest extends TestCase
{
    public function testToStringPreservesAStringValueInTheDatabase(): void
    {
        $literal = new \SqlSemantics\Semantic\Expression\Literal(Dialect::Sqlite, "it's a string");
        $db = new PDO('sqlite::memory:');
        $result = $db->query('SELECT ' . $literal->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame("it's a string", $result->fetchColumn());
    }
}
