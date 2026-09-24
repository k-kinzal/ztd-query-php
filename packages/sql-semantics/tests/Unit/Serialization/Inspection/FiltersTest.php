<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Inspection\Filters;

#[CoversClass(Filters::class)]
#[Medium]
final class FiltersTest extends TestCase
{
    public function testWriteKeepsThePatternSpellingAndWritesConditionsFromOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $pattern = $binder->bind("SHOW DATABASES LIKE 'a''b'");
        $condition = $binder->bind("SHOW DATABASES WHERE `Database` = 'x'");
        self::assertInstanceOf(ShowDatabasesStatement::class, $pattern);
        self::assertInstanceOf(ShowDatabasesStatement::class, $condition);
        self::assertSame([], Filters::write(null));
        self::assertSame("LIKE 'a''b'", implode(' ', array_map(static fn (Tree $part): string => $part->toString(), Filters::write($pattern->filter))));
        self::assertSame("WHERE (`Database` = 'x')", implode(' ', array_map(static fn (Tree $part): string => $part->toString(), Filters::write($condition->filter))));
    }

    public function testDatabaseQuotesTheSelectorOrWritesNothing(): void
    {
        self::assertSame([], Filters::database(null));
        self::assertSame('FROM `a``b`', implode(' ', array_map(static fn (Tree $part): string => $part->toString(), Filters::database('a`b'))));
    }
}
