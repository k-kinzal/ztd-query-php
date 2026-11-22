<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Facade\StatementBinder;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(BoundStatement::class)]
#[Small]
final class BoundStatementTest extends TestCase
{
    public function testFactsAreDerivedFromTheBoundStatement(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $context = $platform->context($profile, null, [], true);
        $binding = (new StatementBinder($platform->parser($profile)))->resolve('SELECT 1 AS answer', $context);

        $copy = new BoundStatement($context, $binding->statement);

        self::assertSame($context, $copy->context);
        self::assertSame('answer', $copy->facts->output?->fields()?->at(0)->name?->value);
    }
}
