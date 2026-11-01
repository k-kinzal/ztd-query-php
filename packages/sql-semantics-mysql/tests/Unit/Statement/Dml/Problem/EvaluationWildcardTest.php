<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(EvaluationWildcard::class)]
#[Small]
final class EvaluationWildcardTest extends TestCase
{
    public function testMessageRetainsTheQualifiedExpansion(): void
    {
        $problem = new EvaluationWildcard(new QualifiedName(new Name('t'), new Name('db')));

        self::assertSame("Unknown table 'db.t'", $problem->message());
    }
}
