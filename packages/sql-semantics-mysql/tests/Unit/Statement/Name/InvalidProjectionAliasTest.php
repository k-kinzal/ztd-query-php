<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Name\AliasRule;
use SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias;

#[CoversClass(InvalidProjectionAlias::class)]
#[Medium]
final class InvalidProjectionAliasTest extends TestCase
{
    public function testMessagePreservesAForwardReferenceAndItsUnresolvedCandidate(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (SELECT x), 1 AS x');
        $problem = $operation->facts->diagnostics[0];

        self::assertInstanceOf(InvalidProjectionAlias::class, $problem);
        self::assertSame(AliasRule::Forward, $problem->rule);
        self::assertSame("Reference 'x' not supported (forward reference in item list)", $problem->message());
        self::assertSame([$operation->field('x')->expression], $problem->candidates);
    }
}
