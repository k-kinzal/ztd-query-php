<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Conditions::class)]
#[Medium]
final class ConditionsTest extends TestCase
{
    public function testDeriveReportsAConditionThatIsNotBoolean(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $context = [];
        array_push($context, ...$semantics->inspect('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->inspect('CREATE TABLE n (a int CHECK (a), b bool CHECK (b), c text CHECK (\'t\'))', $context);
        self::assertSame([
          0 => 'argument of CHECK must be type boolean',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }
}
