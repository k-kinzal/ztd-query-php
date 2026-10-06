<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes::class)]
#[Medium]
final class AttributesTest extends TestCase
{
    public function testCheckedRefusesAnotherValue(): void
    {
        $this->expectExceptionMessage('Constraint attributes are attribute values.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes())->checked([\SqlSemantics\Platform\PostgreSql\Statement\Table\View\RuleEvent::Insert]);
    }

    public function testReportReportsConflictsAndAttributesTheKindDoesNotAdmit(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int, CHECK (a > 0) DEFERRABLE NOT DEFERRABLE, UNIQUE (a) INITIALLY DEFERRED INITIALLY IMMEDIATE)', []);
        self::assertSame([
          0 => 'conflicting constraint properties',
          1 => 'CHECK constraints cannot be marked DEFERRABLE',
          2 => 'conflicting constraint properties',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }
}
