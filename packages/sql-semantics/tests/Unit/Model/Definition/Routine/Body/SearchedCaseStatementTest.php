<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\SearchedCaseStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SearchedCaseStatement::class)]
#[Medium]
final class SearchedCaseStatementTest extends TestCase
{
    public function testWritesBranchesInOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) CASE WHEN a > 0 THEN DO 1; ELSE DO 2; END CASE');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(SearchedCaseStatement::class, $statement->body);
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer) CASE WHEN (`a` > 0) THEN DO 1; ELSE DO 2; END CASE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRequiresABranch(): void
    {
        $this->expectException(InvalidStructure::class);
        new SearchedCaseStatement([]);
    }
}
