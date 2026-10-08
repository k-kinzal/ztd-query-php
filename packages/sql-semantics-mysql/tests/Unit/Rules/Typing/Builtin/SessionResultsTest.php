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

        self::assertEquals(Domain::string(288, Collation::known('utf8mb3_general_ci'), Field::VarString, Coercibility::SystemConstant), $rules['USER'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertSame(5, $rules['VERSION'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))?->length);
    }

    public function testRulesAreUtf8mb3MetadataNames(): void
    {
        $rules = (new SessionResults())->rules();

        self::assertEquals(Domain::string(64, Collation::known('utf8mb3_general_ci'), Field::VarString, Coercibility::Coercible), $rules['COLLATION'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::string(64, Collation::known('utf8mb3_general_ci'), Field::VarString, Coercibility::SystemConstant), $rules['DATABASE'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }

    public function testRulesTypeTheActiveRolesAsALongText(): void
    {
        $rules = (new SessionResults())->rules();

        self::assertEquals(Domain::string(50331648, Collation::known('utf8mb3_general_ci'), Field::LongBlob, Coercibility::SystemConstant), $rules['CURRENT_ROLE'](new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }
}
