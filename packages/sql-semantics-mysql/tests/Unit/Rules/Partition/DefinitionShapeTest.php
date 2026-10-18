<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Partition\DefinitionShape;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue;
use SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DefinitionShape::class)]
#[Small]
final class DefinitionShapeTest extends TestCase
{
    public function testCheckRejectsAnExplicitListAfterAnImplicitOne(): void
    {
        $definitions = [new PartitionDefinition(new Name('p0'), null, [], []), new PartitionDefinition(new Name('p1'), null, [], [new SubpartitionDefinition(new Name('s1'), [])])];
        $this->expectExceptionMessage('Wrong number of subpartitions defined, mismatch with previous setting');
        (new DefinitionShape())->check($definitions);
    }

    public function testProblemChecksTheMethodBeforeSubpartitionCounts(): void
    {
        $definition = new PartitionDefinition(new Name('p'), null, [], []);
        $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod(\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
        self::assertSame(DefinitionIssue::MissingRangeValues, (new DefinitionShape())->problem($definition, $method, 2));
        self::assertSame(DefinitionIssue::SubpartitionCount, (new DefinitionShape())->problem($definition, null, 2));
    }
}
