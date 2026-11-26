<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference;
use SqlSemantics\Testing\MySql\Inspection;

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
        $operation = (new Inspection())->diagnose('WITH RECURSIVE c AS (SELECT x FROM c) SELECT 1', []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::RecursiveWithoutAnchor, $operation->facts->diagnostics[0]->rule);
    }
}
