<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RecursiveReference::class)]
#[Medium]
final class RecursiveReferenceTest extends TestCase
{
    public function testDescribeNamesTheTable(): void
    {
        self::assertSame('the nonrecursive part of recursive common table c', (new RecursiveReference(new Name('c')))->describe());
    }

    public function testAReferenceWithoutAnchorDependsOnTheMissingPart(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('WITH RECURSIVE c AS (SELECT x FROM c) SELECT 1 FROM c', []);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        self::assertTrue($statement->hasExecutionCondition());
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($statement->query);

        self::assertInstanceOf(Misuse::class, $problem);
        self::assertSame(MisuseRule::RecursiveWithoutUnion, $problem->rule);
    }
}
