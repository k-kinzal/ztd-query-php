<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\IndexKeyCoverage;

#[CoversClass(IndexKeyCoverage::class)]
#[Small]
final class IndexKeyCoverageTest extends TestCase
{
    public function testCoversDistinguishesHiddenOrderedAndExactUniqueFields(): void
    {
        $a = new Name('a');
        $b = new Name('b');
        $explicit = new IndexDeclaration(new Name('secondary'), PhysicalIndexKind::Index, true, leadingFields: [$a]);
        $primary = new IndexDeclaration(new Name('PRIMARY'), PhysicalIndexKind::Primary, true, leadingFields: [$b]);
        $check = new IndexKeyCoverage();

        self::assertTrue($check->covers([new Name('A'), $b], $explicit, $primary, false));
        self::assertFalse($check->covers([$b, $a], $explicit, $primary, false));
        self::assertFalse($check->covers([$a, $b], $explicit, null, false));
        self::assertFalse($check->covers([$a], $explicit, $primary, true));
        self::assertTrue($check->covers([$b], $primary, null, true));
    }

    public function testClusterPrefersThePhysicalPrimaryKey(): void
    {
        $unique = new IndexDeclaration(new Name('candidate'), PhysicalIndexKind::Unique, true, clusterCandidate: true);
        $primary = new IndexDeclaration(new Name('PRIMARY'), PhysicalIndexKind::Primary, true);
        $check = new IndexKeyCoverage();

        self::assertSame($primary, $check->cluster([$unique, $primary]));
        self::assertSame($unique, $check->cluster([$unique]));
        self::assertNull($check->cluster([]));
    }
}
