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
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\SpecialResults;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(SpecialResults::class)]
#[Small]
final class SpecialResultsTest extends TestCase
{
    public function testRulesTypeTheDigestsVectorsAndWaits(): void
    {
        $rules = (new SpecialResults())->rules();
        $call = new Invocation([Domain::null()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals([
            Domain::string(64, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible),
            new Domain(Kind::String, Field::Vector, 65532, Domain::NOT_FIXED, false, Collation::binary(), [], Coercibility::Coercible),
            Domain::string(1048512, Collation::known('utf8mb4_0900_ai_ci'), Field::MediumBlob, Coercibility::Coercible),
            Domain::integer(Field::LongLong, 10),
            Domain::integer(),
        ], [$rules['STATEMENT_DIGEST']($call), $rules['TO_VECTOR']($call), $rules['VECTOR_TO_STRING']($call), $rules['VECTOR_DIM']($call), $rules['SOURCE_POS_WAIT']($call)]);
    }

    public function testDocumentIsSixteenMebicharactersOfTheWidestCharacter(): void
    {
        $call = new Invocation([Domain::string(5, Collation::known('latin1_swedish_ci'), Field::VarString, Coercibility::Coercible), Domain::string(2, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $nulls = new Invocation([Domain::null(), Domain::null()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame([[67108864, 'utf8mb4_0900_ai_ci', Field::LongBlob], [16777216, 'binary', Field::LongBlob]], [
            [(new SpecialResults())->document($call, $call->domains, 'extractvalue')?->length, (new SpecialResults())->document($call, $call->domains, 'extractvalue')?->collation->name, (new SpecialResults())->document($call, $call->domains, 'extractvalue')?->field],
            [(new SpecialResults())->document($nulls, $nulls->domains, 'extractvalue')?->length, (new SpecialResults())->document($nulls, $nulls->domains, 'extractvalue')?->collation->name, (new SpecialResults())->document($nulls, $nulls->domains, 'extractvalue')?->field],
        ]);
    }

    public function testSubtractionFollowsTheLengthsOfTheArguments(): void
    {
        $utf8mb4 = Collation::known('utf8mb4_0900_ai_ci');
        $latin1 = Collation::known('latin1_swedish_ci');
        $semantics = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $forty = Domain::string(40, $utf8mb4);
        $empty = Domain::string(0, $utf8mb4, Field::VarString, Coercibility::Coercible);
        $text = Domain::string(65535, $utf8mb4, Field::Blob);

        self::assertSame([[70, Field::VarString], [470, Field::VarString], [16777216, Field::LongBlob], [1048200, Field::MediumBlob], [16777216, Field::LongBlob], [310, Field::VarString]], [
            [(new SpecialResults())->subtraction(new Invocation([$forty, $empty], [], new Settings($utf8mb4), $semantics))->length, (new SpecialResults())->subtraction(new Invocation([$forty, $empty], [], new Settings($utf8mb4), $semantics))->field],
            [(new SpecialResults())->subtraction(new Invocation([$forty, $forty], [], new Settings($utf8mb4), $semantics))->length, (new SpecialResults())->subtraction(new Invocation([$forty, $forty], [], new Settings($utf8mb4), $semantics))->field],
            [(new SpecialResults())->subtraction(new Invocation([$empty, $empty], [], new Settings($utf8mb4), $semantics))->length, (new SpecialResults())->subtraction(new Invocation([$empty, $empty], [], new Settings($utf8mb4), $semantics))->field],
            [(new SpecialResults())->subtraction(new Invocation([$text, $empty], [], new Settings($utf8mb4), $semantics))->length, (new SpecialResults())->subtraction(new Invocation([$text, $empty], [], new Settings($utf8mb4), $semantics))->field],
            [(new SpecialResults())->subtraction(new Invocation([$forty, $empty], [], new Settings($latin1), $semantics))->length, (new SpecialResults())->subtraction(new Invocation([$forty, $empty], [], new Settings($latin1), $semantics))->field],
            [(new SpecialResults())->subtraction(new Invocation([$empty, $forty], [], new Settings($latin1), $semantics))->length, (new SpecialResults())->subtraction(new Invocation([$empty, $forty], [], new Settings($latin1), $semantics))->field],
        ]);
    }

    public function testSubtractionOverflowsShorterInUtf8mb3From80(): void
    {
        $utf8mb3 = Collation::known('utf8mb3_general_ci');
        $empty = Domain::string(0, $utf8mb3);

        self::assertSame([16777215, 16777216], [
            (new SpecialResults())->subtraction(new Invocation([$empty, $empty], [], new Settings($utf8mb3), new Derivation((new Semantics(Dialect::MySql))->context([]))))->length,
            (new SpecialResults())->subtraction(new Invocation([$empty, $empty], [], new Settings($utf8mb3), new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]))))->length,
        ]);
    }
}
