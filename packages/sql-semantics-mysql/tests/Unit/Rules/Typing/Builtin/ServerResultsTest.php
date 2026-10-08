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
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\ServerResults;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(ServerResults::class)]
#[Small]
final class ServerResultsTest extends TestCase
{
    public function testRulesTypeTheLockingFunctionsAsBigints(): void
    {
        $rules = (new ServerResults())->rules();
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $legacy = new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([])));

        self::assertEquals(
            [Domain::integer(Field::LongLong, 1), Domain::integer(Field::LongLong, 21), Domain::integer(Field::LongLong, 21, true), Domain::integer(Field::LongLong, 10), Domain::integer(Field::LongLong, 21, true)],
            [$rules['GET_LOCK']($call), $rules['SLEEP']($call), $rules['IS_USED_LOCK']($call), $rules['IS_USED_LOCK']($legacy), $rules['PS_THREAD_ID']($call)],
        );
    }

    public function testRulesTypeTheTextsAsUtf8mb3(): void
    {
        $rules = (new ServerResults())->rules();
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $utf8 = Collation::known('utf8mb3_general_ci');

        self::assertEquals(
            [Domain::string(4, $utf8, Field::VarString, Coercibility::SystemConstant), Domain::string(11, $utf8, Field::VarString, Coercibility::Coercible), Domain::string(50331648, $utf8, Field::LongBlob, Coercibility::SystemConstant)],
            [$rules['ICU_VERSION']($call), $rules['FORMAT_PICO_TIME']($call), $rules['ROLES_GRAPHML']($call)],
        );
    }

    public function testRulesTypeAnyValueAsItsArgumentAlone(): void
    {
        $call = new Invocation([Domain::integer(Field::LongLong, 1)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([Field::LongLong, 1], [(new ServerResults())->rules()['ANY_VALUE']($call)?->field, (new ServerResults())->rules()['ANY_VALUE']($call)?->length]);
    }

    public function testConstantReadsAnIntegerAsSigned(): void
    {
        $constant = (new ServerResults())->constant(Domain::integer(Field::LongLong, 20, true));

        self::assertSame([false, 20, Field::LongLong], [$constant->unsigned, $constant->length, $constant->field]);
    }
}
