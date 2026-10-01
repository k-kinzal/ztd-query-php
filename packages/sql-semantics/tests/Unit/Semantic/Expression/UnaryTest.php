<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\Unary::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class UnaryTest extends TestCase
{
    public function testToStringPreservesNullTestMeaning(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT NULL IS NULL AS n');
        $db = new PDO('sqlite::memory:');
        $result = $db->query($statement->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(1, $result->fetchColumn());
    }
}
