<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\SpatialRule;

#[CoversClass(SpatialRule::class)]
#[Medium]
final class SpatialRuleTest extends TestCase
{
    public function testCreateLowersOrReplace(): void
    {
        self::assertSame("CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 7 NAME 'n' DEFINITION 'd'", (new Semantics(Dialect::MySql))->analyze("create or replace spatial reference system 7 name 'n' definition 'd'")->toString());
    }

    public function testAttributesKeepsTheWrittenOrder(): void
    {
        self::assertSame("CREATE SPATIAL REFERENCE SYSTEM 7 DESCRIPTION 'x' DEFINITION 'd' NAME 'n'", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("create spatial reference system 7 description 'x' definition 'd' name 'n'")->toString());
    }

    public function testAttributesRetainsRepeatedRolesBeforeAnIdentifierCondition(): void
    {
        $sql = "CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a' NAME 'b' DEFINITION 'd'";
        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference::class, $statement);
        self::assertCount(3, $statement->attributes);
        self::assertSame('b', $statement->attributes[1]->value->value);
        self::assertSame($sql, $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $statement->warnings()[0]);
        self::assertTrue($statement->warnings()[0]->aborts);
    }

    public function testCompletedPreservesARepeatedAttributeBeforeALaterLineFeed(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = "CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a' NAME 'b' DESCRIPTION 'c\nd'";
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed(new \SqlParser\MySql\MySqlParser('mysql-8.4.7'), $sql, strlen($sql), $semantics->profile());
        self::assertCount(1, $notices);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[0][1]);
        self::assertStringContainsString('Multiple definitions of attribute NAME', $notices[0][1]->problem->message());
    }

    public function testAttributesRefusesAnEarlierLineFeedBeforeARepeatedAttribute(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('line feed');
        (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a\nb' NAME 'c'");
    }
    public function testStatementLowersAnExistingReferenceDrop(): void
    {
        self::assertSame('DROP SPATIAL REFERENCE SYSTEM IF EXISTS 9', (new Semantics(Dialect::MySql))->analyze('DROP SPATIAL REFERENCE SYSTEM IF EXISTS 9')->toString());
    }

    public function testDropRetainsAnOutOfRangeRequest(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP SPATIAL REFERENCE SYSTEM 4294967296');
        self::assertSame('DROP SPATIAL REFERENCE SYSTEM 4294967296', $operation->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference::class, $operation);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::IdentifierOutOfRange, $operation->problem?->rule);
    }

}
