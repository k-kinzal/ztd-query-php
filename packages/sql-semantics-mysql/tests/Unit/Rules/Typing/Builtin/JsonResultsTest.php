<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\JsonResults;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(JsonResults::class)]
#[Small]
final class JsonResultsTest extends TestCase
{
    public function testRulesTypeDocumentsCountsAndStrings(): void
    {
        $rules = (new JsonResults())->rules();
        $call = new Invocation([Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'))], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals(JsonResults::json(), $rules['JSON_EXTRACT']($call));
        self::assertEquals(Domain::integer(Field::LongLong, 21), $rules['JSON_LENGTH']($call));
        self::assertEquals(Domain::integer(Field::LongLong, 1), $rules['JSON_OVERLAPS']($call));
        self::assertEquals(Domain::string(62, Collation::known('utf8mb4_bin'), Field::VarString, Coercibility::Coercible), $rules['JSON_QUOTE']($call));
        self::assertEquals(Domain::string(17, Collation::known('utf8mb4_bin'), Field::VarString, Coercibility::Coercible), $rules['JSON_TYPE']($call));
        self::assertEquals(Domain::string(10, Collation::known('utf8mb4_bin'), Field::VarString, Coercibility::Coercible), $rules['JSON_UNQUOTE']($call));
    }

    public function testRulesTypeTheSchemaFunctionsOfANullSchemaAsNull(): void
    {
        $rules = (new JsonResults())->rules();
        $call = new Invocation([Domain::null(), JsonResults::json()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals([Domain::null(), Domain::null()], [$rules['JSON_SCHEMA_VALID']($call), $rules['JSON_SCHEMA_VALIDATION_REPORT']($call)]);
    }

    public function testJsonAnswersTheBinaryJsonOf57(): void
    {
        self::assertEquals(new Domain(Kind::Json, Field::Json, 4194304, 0, false, Collation::binary()), JsonResults::json(GrammarRelease::MySql5744));
        self::assertSame(16777216, JsonResults::json(GrammarRelease::MySql5744, true)->length);
        self::assertSame(4294967295, JsonResults::json()->length);
    }

    public function testTextIsATextCountedInBytesBeyond16383Characters(): void
    {
        self::assertEquals(Domain::string(16383, Collation::known('utf8mb4_bin'), Field::VarString, Coercibility::Coercible), JsonResults::text(16383));
        self::assertEquals(Domain::string(65536, Collation::known('utf8mb4_bin'), Field::MediumBlob, Coercibility::Coercible), JsonResults::text(16384));
        self::assertEquals(Domain::string(4294967295, Collation::known('utf8mb4_bin'), Field::LongBlob, Coercibility::Coercible), JsonResults::text(4294967295));
    }
}
