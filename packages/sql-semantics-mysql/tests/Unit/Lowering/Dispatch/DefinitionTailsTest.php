<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dispatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\DefinitionTails;

#[CoversClass(DefinitionTails::class)]
#[Medium]
final class DefinitionTailsTest extends TestCase
{
    public function testCreateHandsAViewWithAlgorithmToTheTableDefinitionFamily(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: createView');

        (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('CREATE OR REPLACE ALGORITHM = MERGE VIEW v AS SELECT 1');
    }

    public function testCreateHandsADefinerTailToTheFamilyOfTheObject(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL routine family: create');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("CREATE DEFINER = 'admin'@'localhost' TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @x = 1");
    }

    public function testTailHandsAViewToTheTableDefinitionFamily(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL table definition family: createView');

        (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('CREATE VIEW v AS SELECT 1');
    }

    public function testTailHandsAStoredProgramToTheRoutineFamily(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL routine family: create');

        (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 HOUR DO SELECT 1');
    }
}
