<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\CountResults;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(CountResults::class)]
#[Small]
final class CountResultsTest extends TestCase
{
    public function testRulesAnswerABigIntOfTheReportedWidth(): void
    {
        $rules = (new CountResults())->rules();

        self::assertEquals(Domain::integer(Field::LongLong, 10), $rules['LENGTH'](new Invocation([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::integer(Field::LongLong, 21), $rules['SIGN'](new Invocation([Domain::integer()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::integer(Field::LongLong, 21), $rules['GROUPING'](new Invocation([Domain::integer()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::integer(Field::LongLong, 21, true), $rules['LAST_INSERT_ID'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }
}
