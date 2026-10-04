<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\CopyRule::class)]
#[Medium]
final class CopyRuleTest extends TestCase
{
    public function testCopyLowersAQuery(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('COPY (DELETE FROM t RETURNING *) TO STDOUT');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyQuery::class, $query->statement);
    }

    public function testDirectionLowersFrom(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('COPY t FROM STDIN');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyDirection::From, $statement->direction);
    }

    public function testFlagLowersBinaryAndProgram(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("COPY BINARY t TO PROGRAM 'gzip'");
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        self::assertSame([true, true], [$statement->binary, $statement->program]);
    }

    public function testFileLowersAFileName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("COPY t TO '/tmp/t.csv'");
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        self::assertSame('/tmp/t.csv', $statement->file instanceof \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant ? $statement->file->value : null);
    }

    public function testDelimitersLowersTheString(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("COPY t FROM STDIN DELIMITERS '|'");
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        self::assertSame('|', $statement->delimiters?->value);
    }
}
