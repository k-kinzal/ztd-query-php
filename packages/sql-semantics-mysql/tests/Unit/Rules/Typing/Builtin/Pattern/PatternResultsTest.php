<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin\Pattern;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Pattern\PatternResults;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(PatternResults::class)]
#[Small]
final class PatternResultsTest extends TestCase
{
    public function testRulesResolveTheMatchesAsIntegersAndTheSubstringAsLongAsTheSubject(): void
    {
        $rules = (new PatternResults())->rules();
        $call = new Invocation([Domain::string(10, Collation::known('latin1_swedish_ci')), Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $substring = $rules['REGEXP_SUBSTR']($call);

        self::assertSame([1, 21], [$rules['REGEXP_LIKE']($call)?->length, $rules['REGEXP_INSTR']($call)?->length]);
        self::assertSame([10, 'latin1_swedish_ci'], [$substring?->length, $substring?->collation->name]);
    }

    public function testMatchedReportsCollationsThatDoNotMix(): void
    {
        $call = new Invocation([Domain::string(1, Collation::known('utf8mb4_bin'), Field::VarString, Coercibility::Explicit), Domain::string(1, Collation::known('utf8mb4_0900_as_cs'), Field::VarString, Coercibility::Explicit)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertFalse((new PatternResults())->matched($call, 'regexp_like'));
    }

    public function testReplacedHoldsWholeCharactersOf16777216Bytes(): void
    {
        $results = new PatternResults();
        $utf8 = $results->replaced(new Invocation([Domain::string(3, Collation::known('utf8mb3_general_ci')), Domain::string(1, Collation::known('utf8mb3_general_ci')), Domain::string(1, Collation::known('utf8mb3_general_ci'))], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))));
        $utf16 = $results->replaced(new Invocation([Domain::string(3, Collation::known('utf16_general_ci')), Domain::string(1, Collation::known('utf16_general_ci')), Domain::string(1, Collation::known('utf16_general_ci'))], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))));
        $binary = $results->replaced(new Invocation([Domain::string(3, Collation::binary()), Domain::string(1, Collation::binary()), Domain::string(1, Collation::binary())], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))));

        self::assertSame([[16777215, Field::MediumBlob], [8388608, Field::LongBlob], [16777216, Field::LongBlob]], [[$utf8?->length, $utf8?->field], [$utf16?->length, $utf16?->field], [$binary?->length, $binary?->field]]);
    }

    public function testNullabilityMakesAReplacementOfMultibyteCharactersNullable(): void
    {
        $results = new PatternResults();

        self::assertSame(
            [Nullability::Nullable, Nullability::NotNull, Nullability::NotNull],
            [
                $results->nullability('regexp_replace', Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), Nullability::NotNull),
                $results->nullability('REGEXP_REPLACE', Domain::string(1, Collation::known('latin1_swedish_ci')), Nullability::NotNull),
                $results->nullability('REGEXP_SUBSTR', Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), Nullability::NotNull),
            ],
        );
    }
}
