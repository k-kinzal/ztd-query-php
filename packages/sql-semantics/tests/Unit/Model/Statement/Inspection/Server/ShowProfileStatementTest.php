<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Statement\Inspection\Server\ShowProfileStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowProfileStatement::class)]
#[Medium]
final class ShowProfileStatementTest extends TestCase
{
    public function testResultColumnsAppendTheMeasuredCategories(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertSame([], $statement->categories);
        self::assertNull($statement->query);
        self::assertNull($statement->limit);
        self::assertSame(['Status', 'Duration'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('numeric', $statement->resultColumns()[1]->expression->type->name);
        self::assertSame('SHOW PROFILE', $statement->toString());
    }

    public function testWithCategoriesReplacesTheRequestImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE CPU FOR QUERY 7');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        $changed = $statement->withCategories([ProfileCategory::Swaps, ProfileCategory::Cpu]);
        self::assertNotSame($statement, $changed);
        self::assertSame([ProfileCategory::Cpu], $statement->categories);
        self::assertSame(['Status', 'Duration', 'CPU_user', 'CPU_system', 'Swaps'], array_column($changed->resultColumns(), 'name'));
        self::assertSame('SHOW PROFILE SWAPS, CPU FOR QUERY 7', $changed->toString());
    }

    public function testWithQuerySelectsAnotherProfiledStatementImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW PROFILE FOR QUERY 7');
        $other = $binder->bind('SHOW PROFILE FOR QUERY 0031');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertInstanceOf(ShowProfileStatement::class, $other);
        $changed = $statement->withQuery($other->query);
        self::assertNotSame($statement, $changed);
        self::assertSame('7', $statement->query?->text);
        self::assertSame('SHOW PROFILE FOR QUERY 0031', $changed->toString());
        self::assertSame('SHOW PROFILE', $changed->withQuery(null)->toString());
    }

    public function testWithLimitReplacesTheRowWindowImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW PROFILE ALL LIMIT 3');
        $other = $binder->bind('SHOW PROFILE LIMIT 2, 5');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertInstanceOf(ShowProfileStatement::class, $other);
        $changed = $statement->withLimit($other->limit);
        self::assertNotSame($statement, $changed);
        self::assertSame('3', $statement->limit?->count->spelling());
        self::assertSame('SHOW PROFILE ALL LIMIT 5 OFFSET 2', $changed->toString());
        self::assertSame('SHOW PROFILE ALL', $changed->withLimit(null)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE CPU FOR QUERY 7 LIMIT 3');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->categories, $statement->query, $statement->limit], [$copy->categories, $copy->query, $copy->limit]);
    }

    public function testRejectsANonNumericQuerySelector(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW PROFILE');
        $pattern = $binder->bind("SHOW DATABASES LIKE 'x'");
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertInstanceOf(ShowDatabasesStatement::class, $pattern);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Inspection\Filter\PatternFilter::class, $pattern->filter);
        $this->expectException(InvalidStructure::class);
        new ShowProfileStatement($statement->origin, [], $pattern->filter->pattern);
    }
}
