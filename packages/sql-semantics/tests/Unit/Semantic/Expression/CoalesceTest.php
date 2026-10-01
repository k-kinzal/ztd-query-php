<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\Coalesce::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CoalesceTest extends TestCase
{
    public function testToStringRetainsFirstNonNullOrdering(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT COALESCE(NULL, 3, 4) AS n');
        self::assertSame(\SqlSemantics\Core\Type\Nullability::NotNull, $statement->field('n')->expression->nullability);
        $db = new PDO('sqlite::memory:');
        $result = $db->query($statement->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(3, $result->fetchColumn());
    }
}
