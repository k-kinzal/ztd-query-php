<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\TableUse::class)]
#[Medium]
final class TableUseTest extends TestCase
{
    public function testInitializeRelationInformationBindsAWriteDestination(): void
    {
        $target = new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $information = new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([]));
        $target->initializeRelationInformation($information);
        self::assertSame($information, $target->relationInformation());
    }

    public function testRelationInformationRejectsAnUnresolvedDestination(): void
    {
        $target = new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $this->expectExceptionMessage('A relation occurrence must be resolved before use.');
        $target->relationInformation();
    }

    public function testHasRelationInformationDistinguishesTheUnresolvedTarget(): void
    {
        $target = new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        self::assertFalse($target->hasRelationInformation());
        $target->initializeRelationInformation(new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([])));
        self::assertTrue($target->hasRelationInformation());
    }
}
