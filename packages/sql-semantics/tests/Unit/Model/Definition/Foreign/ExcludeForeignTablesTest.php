<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables;
use SqlSemantics\Model\Definition\Foreign\ForeignRelation;
use SqlSemantics\Model\Relation\QualifiedName;

#[CoversClass(ExcludeForeignTables::class)]
#[Medium]
final class ExcludeForeignTablesTest extends TestCase
{
    public function testSelectionRetainsRemoteRelationOrderAndScope(): void
    {
        $first = new ForeignRelation(new QualifiedName(['ext', 'a']), false);
        $second = new ForeignRelation(new QualifiedName(['b']));
        $selection = new ExcludeForeignTables([$first, $second]);
        self::assertSame([$first, $second], $selection->tables);
        self::assertFalse($selection->tables[0]->includeDescendants);
        self::assertTrue($selection->tables[1]->includeDescendants);
    }

}
