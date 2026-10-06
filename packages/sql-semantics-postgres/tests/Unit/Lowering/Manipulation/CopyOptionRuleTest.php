<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\CopyOptionRule::class)]
#[Medium]
final class CopyOptionRuleTest extends TestCase
{
    public function testOptionsLowersTheOldSyntax(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("COPY t TO STDOUT WITH CSV HEADER QUOTE '\"' FORCE QUOTE *");
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        self::assertCount(4, $statement->legacy);
    }

    public function testItemLowersAForceColumnList(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('COPY t FROM STDIN CSV FORCE NOT NULL a, b');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        $force = $statement->legacy[1];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForce::class, $force);
        self::assertCount(2, $force->columns);
    }

    public function testOptionLowersTheName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('COPY t FROM STDIN ("Format" csv)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        self::assertSame('Format', $statement->options[0]->name->value);
    }

    public function testArgumentLowersEveryForm(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("COPY t FROM STDIN (a, b 1, c *, d DEFAULT, e (x), f 'y', g true)");
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        self::assertSame(7, count($statement->options));
    }

    public function testArgumentsLowersTheItems(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("COPY t FROM STDIN (force_null (a, 'b', off))");
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, $statement);
        self::assertCount(3, $statement->options[0]->argument instanceof \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyArguments ? $statement->options[0]->argument->items : []);
    }
}
