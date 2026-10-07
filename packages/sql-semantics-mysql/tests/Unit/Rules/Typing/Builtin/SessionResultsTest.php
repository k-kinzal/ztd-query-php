<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\SessionResults;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(SessionResults::class)]
#[Small]
final class SessionResultsTest extends TestCase
{
    public function testRulesAreSystemConstantNames(): void
    {
        $rules = (new SessionResults())->rules();

        self::assertEquals(Domain::string(288, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::SystemConstant), $rules['USER'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertSame(5, $rules['VERSION'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))?->length);
    }
}
