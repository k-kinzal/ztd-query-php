<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\Binary::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class BinaryTest extends TestCase
{
    public function testToStringPreservesNonAssociativeOperandGrouping(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT 10 - (3 - 1) AS n');
        $db = new PDO('sqlite::memory:');
        $result = $db->query($statement->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(8, $result->fetchColumn());
    }
}
