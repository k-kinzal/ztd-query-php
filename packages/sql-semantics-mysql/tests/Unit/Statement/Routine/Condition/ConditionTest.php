<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Condition::class)]
#[Medium]
final class ConditionTest extends TestCase
{
    public function testImplementationsAreTheValuesAHandlerNames(): void
    {
        $create = (new Inspection())->inspect("CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 1051; DECLARE CONTINUE HANDLER FOR SQLWARNING, 1052, SQLSTATE '42S02', c BEGIN END; END");
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $handler = $body->declarations[1];
        self::assertInstanceOf(HandlerDeclaration::class, $handler);

        self::assertSame(
            [GeneralCondition::class, ErrorCode::class, SqlState::class, ConditionName::class],
            array_map(static fn (Condition $condition): string => $condition::class, $handler->conditions),
        );
        self::assertNotContains(Condition::class, class_implements(SignalItem::class));
    }
}
