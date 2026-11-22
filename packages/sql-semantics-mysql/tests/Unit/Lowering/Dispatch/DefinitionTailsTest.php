<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dispatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\DefinitionTails;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DefinitionTails::class)]
#[Medium]
final class DefinitionTailsTest extends TestCase
{
    public function testCreateHandsAViewWithAlgorithmToTheTableDefinitionFamily(): void
    {
        $operation = (new Inspection('mysql-8.4.7'))->inspect('CREATE OR REPLACE ALGORITHM = MERGE VIEW v AS SELECT 1');

        self::assertStringStartsWith('SqlSemantics\\Platform\\MySql\\Statement\\View\\', $operation->statement::class);
        self::assertSame('CREATE OR REPLACE ALGORITHM = MERGE VIEW v AS SELECT 1', $operation->toString());
    }

    public function testCreateHandsADefinerTailToTheFamilyOfTheObject(): void
    {
        $operation = (new Inspection('mysql-5.7.44'))->inspect("CREATE DEFINER = 'admin'@'localhost' TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @x = 1");

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        self::assertSame('CREATE DEFINER = admin@localhost TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @x = 1', $operation->toString());
    }

    public function testTailHandsAViewToTheTableDefinitionFamily(): void
    {
        $operation = (new Inspection('mysql-5.6.51'))->inspect('CREATE VIEW v AS SELECT 1');

        self::assertStringStartsWith('SqlSemantics\\Platform\\MySql\\Statement\\View\\', $operation->statement::class);
        self::assertSame('CREATE VIEW v AS SELECT 1', $operation->toString());
    }

    public function testTailHandsAStoredProgramToTheRoutineFamily(): void
    {
        $operation = (new Inspection('mysql-9.1.0'))->inspect('CREATE EVENT e ON SCHEDULE EVERY 1 HOUR DO SELECT 1');

        self::assertInstanceOf(CreateEvent::class, $operation->statement);
        self::assertSame('CREATE EVENT e ON SCHEDULE EVERY 1 HOUR DO SELECT 1', $operation->toString());
    }
}
