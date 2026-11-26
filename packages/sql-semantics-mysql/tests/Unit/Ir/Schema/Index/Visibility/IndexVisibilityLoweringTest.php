<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Visibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\IndexVisibilityLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SecondaryIndexKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SetIndexVisibility;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(IndexVisibilityLowering::class)]
#[Medium]
final class IndexVisibilityLoweringTest extends TestCase
{
    public function testEditsRecognizesAPureNativeVisibilityGroup(): void
    {
        $subject = 'ALTER TABLE app.t ALGORITHM=COPY, ALTER INDEX idx INVISIBLE, ALTER INDEX idx VISIBLE';
        $inspected = (new Inspection())->inspect($subject)->statement;
        self::assertInstanceOf(AlterTable::class, $inspected);
        $edits = (new IndexVisibilityLowering())->edits($inspected);
        self::assertNotNull($edits);
        self::assertSame([false,true], array_column($edits, 'visible'));
    }

    public function testLowerRetainsTheTemporaryOwnerAndExactKeySpelling(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE app.t (a INT, KEY ordinary_key(a))',
            'CREATE TEMPORARY TABLE app.t (a INT, UNIQUE KEY Physical_Key(a))',
        ]);
        $result = $semantics->analyze('ALTER TABLE app.t ALTER INDEX physical_key INVISIBLE', $context);

        self::assertInstanceOf(SetIndexVisibility::class, $result);
        self::assertSame(ExternalRelationKind::TemporaryTable, $result->owner->kind);
        self::assertSame('Physical_Key', $result->edits[0]->index->value);
        self::assertSame(SecondaryIndexKind::Unique, $result->edits[0]->kind);
    }

    public function testEditRecognizesASecondaryKeyWithNonOrdinaryParts(): void
    {
        $known = new IndexDeclaration(new Name('Geo_Key'), PhysicalIndexKind::Spatial, false);
        $changed = (new IndexVisibilityLowering())->edit(new IndexVisibility(new Name('geo_key'), false), [$known]);

        self::assertSame('Geo_Key', $changed->index->value);
        self::assertSame(SecondaryIndexKind::Spatial, $changed->kind);
    }
}
