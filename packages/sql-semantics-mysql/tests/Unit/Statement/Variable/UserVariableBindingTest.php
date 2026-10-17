<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UserVariableBinding::class)]
#[Medium]
final class UserVariableBindingTest extends TestCase
{
    public function testKeepsTheOccurrenceNameAndEntryState(): void
    {
        $binding = new UserVariableBinding(new Name('v'), false);

        self::assertSame(['v', false], [$binding->name->value, $binding->exists]);
    }

    public function testDescribesReadsBeforeAndAfterAnAssignment(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: []);
        $context = $semantics->context([], session: $settings);
        $query = $semantics->analyze('SELECT @v AS first, @v:=1, @V AS last', $context);
        $first = $query->field('first')->resolution;
        $last = $query->field('last')->resolution;
        self::assertInstanceOf(UserVariableBinding::class, $first);
        self::assertInstanceOf(UserVariableBinding::class, $last);

        self::assertFalse($first->exists);
        self::assertTrue($last->exists);
        self::assertSame([], $settings->userVariables);
        self::assertEquals($first, $semantics->analyze('SELECT @v AS first', $context)->field('first')->resolution);
    }
}
