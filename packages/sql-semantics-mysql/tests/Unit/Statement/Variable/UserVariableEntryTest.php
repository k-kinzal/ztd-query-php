<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(UserVariableEntry::class)]
#[Small]
final class UserVariableEntryTest extends TestCase
{
    public function testResolvingAssignmentsPreservesTheSuppliedEntrySnapshot(): void
    {
        $entry = new UserVariableEntry(Domain::integer(), Collation::known('latin1_swedish_ci'));
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: ['V' => $entry]);
        $semantics = new Semantics(Dialect::MySql);
        $query = $semantics->analyze("SELECT @v:=_utf8mb4'new',@v", $semantics->context([], session: $settings));

        self::assertEquals(new Known(Domain::integer()), $query->field(1)->type);
        self::assertSame($entry, $settings->userVariables['v'] ?? null);
        self::assertSame('latin1', $entry->collation->charset->name);
        self::assertSame('binary', $entry->domain->collation->charset->name);
    }
    public function testRetainsBinaryStringInterpretationIndependentlyOfTheReadDomain(): void
    {
        $entry = new UserVariableEntry(Domain::integer(), Collation::binary(), true);
        self::assertSame(Domain::integer()->kind, $entry->domain->kind);
        self::assertTrue($entry->binaryString);
        self::assertNull((new UserVariableEntry(Domain::integer(), Collation::binary()))->binaryString);
    }

}
