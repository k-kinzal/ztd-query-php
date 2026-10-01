<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\NullIf::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class NullIfTest extends TestCase
{
    public function testToStringRetainsEqualitySensitiveNullProduction(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT NULLIF(3, 3) AS n');
        $db = new PDO('sqlite::memory:');
        $result = $db->query($statement->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertNull($result->fetchColumn());
    }
}
