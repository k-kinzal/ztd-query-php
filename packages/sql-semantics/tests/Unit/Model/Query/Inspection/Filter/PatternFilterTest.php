<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Statement\Inspection\Server\ShowProfileStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PatternFilter::class)]
#[Medium]
final class PatternFilterTest extends TestCase
{
    public function testPatternKeepsItsOriginalSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW DATABASES LIKE 'app\\_%'");
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertSame("'app\\_%'", $statement->filter->pattern->text);
        self::assertSame(LiteralKind::Text, $statement->filter->pattern->literalKind);
        self::assertSame("SHOW DATABASES LIKE 'app\\_%'", $statement->toString());
    }

    public function testRejectsANonTextPattern(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE FOR QUERY 7');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertNotNull($statement->query);
        $this->expectException(InvalidStructure::class);
        new PatternFilter($statement->query);
    }
}
