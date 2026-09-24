<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\PostgreSql\TablespaceInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace\SetTablespaceOptionsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\ImpliedSetting;
use SqlSemantics\Schema\Storage\Parameter;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TablespaceInvariant::class)]
#[Medium]
final class TablespaceInvariantTest extends TestCase
{
    public function testParametersAcceptsKnownOverridesAndRejectsImpliedValues(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TABLESPACE t SET (seq_page_cost = 2, maintenance_io_concurrency = '10')");
        self::assertInstanceOf(SetTablespaceOptionsStatement::class, $statement);
        TablespaceInvariant::parameters($statement->parameters);
        $this->expectException(InvalidStructure::class);
        TablespaceInvariant::parameters([new Parameter(new QualifiedName(['seq_page_cost']), ImpliedSetting::Enabled)]);
    }

    public function testNamesRejectsMoreThanANamespaceAndAName(): void
    {
        TablespaceInvariant::names([new QualifiedName(['a', 'b'])]);
        $this->expectException(InvalidStructure::class);
        TablespaceInvariant::names([new QualifiedName(['a', 'b', 'c'])]);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['seq_page_cost', '1.5', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['seq_page_cost', '-0', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['seq_page_cost', '-1', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['random_page_cost', 'abc', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['effective_io_concurrency', '1000', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['effective_io_concurrency', '1001', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['maintenance_io_concurrency', '-1', false])]
    public function testAcceptsChecksTheParameterDomain(string $name, string $value, bool $expected): void
    {
        self::assertSame($expected, TablespaceInvariant::accepts($name, $value));
    }

    #[\PHPUnit\Framework\Attributes\TestWith([' 1.5 ', 1.5])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1e3', 1000.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['+.5', 0.5])]
    #[\PHPUnit\Framework\Attributes\TestWith(['0x10', 16.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['0x1.8p1', 3.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['-Infinity', -INF])]
    #[\PHPUnit\Framework\Attributes\TestWith(['NaN', null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1e400', null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1e-400', null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['0x', null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1e', null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['', null])]
    public function testRealReadsTheValueAsStrtodDoes(string $value, ?float $expected): void
    {
        self::assertSame($expected, TablespaceInvariant::real($value));
    }

    #[\PHPUnit\Framework\Attributes\TestWith([' 7 ', 7.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['010', 8.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['0x3E8', 1000.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['-5', -5.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1.5', 2.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['2.5', 2.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1e2', 100.0])]
    #[\PHPUnit\Framework\Attributes\TestWith(['08', null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1.5x', null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['abc', null])]
    public function testIntegerReadsTheValueAsPostgreSqlIntegerOptionsDo(string $value, ?float $expected): void
    {
        self::assertSame($expected, TablespaceInvariant::integer($value));
    }

    public function testParametersRejectsARepeatedOrOutOfDomainParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE t SET (seq_page_cost = 2)');
        self::assertInstanceOf(SetTablespaceOptionsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        TablespaceInvariant::parameters([...$statement->parameters, ...$statement->parameters]);
    }
}
