<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Statement\Inspection\Replication\LogEventPosition;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogEventsStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LogEventPosition::class)]
#[Medium]
final class LogEventPositionTest extends TestCase
{
    public function testCheckAcceptsANumericLiteralOrNoPosition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW BINLOG EVENTS FROM 4');
        self::assertInstanceOf(ShowBinaryLogEventsStatement::class, $statement);
        LogEventPosition::check($statement->position);
        LogEventPosition::check(null);
        self::assertSame('4', $statement->position?->text);
    }

    public function testCheckRejectsATextLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW DATABASES LIKE 'x'");
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        $this->expectException(InvalidStructure::class);
        LogEventPosition::check($statement->filter->pattern);
    }
}
