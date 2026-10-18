<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainTargets;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedExplainTargets::class)]
#[Small]
final class RefusedExplainTargetsTest extends TestCase
{
    public function testMessageRetainsTheNamespaceAndTheOuterDatabase(): void
    {
        $target = new QualifiedName(new Name('x'));
        $table = new TableReference(new QualifiedName(new Name('t')), new Name('y'));
        $targets = new RefusedDeleteTargets([$target], [$table]);
        $problem = new RefusedExplainTargets($targets, database: new Name('d'));

        self::assertSame($targets, $problem->targets);
        self::assertSame('d', $problem->database?->value);
        self::assertSame($targets->message(), $problem->message());
    }
}
