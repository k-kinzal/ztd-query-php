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
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\TextResults;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(TextResults::class)]
#[Small]
final class TextResultsTest extends TestCase
{
    public function testTrimPreservesItsNullSubjectBeforeCombiningRemovalText(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT TRIM('x' FROM NULL)");
        $type = $operation->field(0)->type;

        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $type);
        self::assertInstanceOf(Domain::class, $type->descriptor);
        self::assertSame(['binary', 0], [$type->descriptor->collation->name, $type->descriptor->length]);
    }

    public function testBoundedKeepsLegacyBinaryConcatenationCharacterWidths(): void
    {
        $collation = Collation::known('utf8mb4_general_ci');
        $call = new Invocation([Domain::string(1, Collation::binary(), Field::VarString, Coercibility::Coercible), Domain::string(4, $collation, Field::VarString, Coercibility::Coercible)], [], new Settings($collation), new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([])));

        self::assertSame(5, (new TextResults())->bounded($call, 5, 'concat')?->length);
    }

    public function testBoundedCapsLongBlobMetadataBeforeResultConversion(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $call = new Invocation([Domain::string(16777215, $collation, Field::Blob)], [], new Settings($collation), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $results = new TextResults();

        self::assertSame(4194304, $results->bounded($call, 16777215, 'concat')?->length);
        self::assertSame(16777216, $results->bounded($call, 16777215, 'concat')->metadataLength());
        self::assertSame(Field::LongBlob, $results->bounded($call, 16777215, 'concat')->field);
        self::assertSame(1024, $results->bounded($call, 1024, 'concat')?->length);
    }

    public function testBoundedRoundsTheByteCapToWholeCharactersBeforeSelectingTheField(): void
    {
        $collation = Collation::known('utf8mb3_general_ci');
        $call = new Invocation([Domain::string(50331645, $collation, Field::LongBlob)], [], new Settings($collation), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $domain = (new TextResults())->bounded($call, 16777215, 'concat');

        self::assertSame([Field::MediumBlob, 5592405, 16777215], [$domain?->field, $domain?->length, $domain?->metadataLength()]);
    }

    public function testBoundedRetainsTheCharacterBoundThroughNestedConcatenation(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $call = new Invocation([Domain::string(65535, $collation, Field::Blob)], [], new Settings($collation), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $rules = (new TextResults())->rules();
        $first = $rules['CONCAT']($call);

        self::assertNotNull($first);
        self::assertSame([65535, 262140], [$first->length, $first->metadataLength()]);
        self::assertEquals($first, $rules['CONCAT'](new Invocation([$first], [], $call->settings, $call->derivation)));
    }

    public function testConcatenatedBytesCountsEncodedTemporalBoundsAndSeparators(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $date = new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Date, Field::Date, 10, collation: $collation, coercibility: Coercibility::Coercible);
        $binary = Domain::string(1, Collation::binary(), Field::VarString, Coercibility::Coercible);
        $call = new Invocation([$binary, $date, $date, $date], [], new Settings($collation), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $rules = new TextResults();

        self::assertSame(121, $rules->concatenatedBytes($call, false));
        self::assertSame(122, $rules->concatenatedBytes($call, true));
        self::assertSame(122, $rules->bounded($call, 32, 'concat_ws')?->length);
    }

    public function testRulesSumConcatenationsAndCountSeparators(): void
    {
        $rules = (new TextResults())->rules();

        self::assertSame(5, $rules['CONCAT'](new Invocation([Domain::string(2, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))?->length);
        self::assertSame(6, $rules['CONCAT_WS'](new Invocation([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::string(2, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))?->length);
        self::assertSame(3, $rules['REPEAT'](new Invocation([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::integer()], [new StringLiteral(['a']), new NumberLiteral('3')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))?->length);
    }

    public function testConcatenatedLengthUsesTheVariableCharsetBeforeCappingBytes(): void
    {
        $collation = Collation::known('utf8mb4_general_ci');
        $variable = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('b'));
        $call = new Invocation([Domain::string(16777215, $collation, Field::MediumBlob)], [new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($variable)], new Settings($collation), new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([])));
        $results = new TextResults();
        $domain = $results->rules()['CONCAT']($call);

        self::assertSame(4194303, $results->concatenatedLength($call, 0));
        self::assertSame([Field::MediumBlob, 16777212], [$domain?->field, $domain?->metadataLength()]);
    }

    public function testLeadingTakesTheLiteralCountAtMostTheString(): void
    {
        self::assertSame(2, (new TextResults())->leading(new Invocation([Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::integer()], [new StringLiteral(['abc']), new NumberLiteral('2')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertSame(3, (new TextResults())->leading(new Invocation([Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::integer()], [new StringLiteral(['abc']), new NumberLiteral('9')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }

    public function testSubstringCountsWhatRemainsAfterThePosition(): void
    {
        $text = new StringLiteral(['abcdef']);

        self::assertSame(5, (new TextResults())->substring(new Invocation([Domain::string(6, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::integer()], [$text, new NumberLiteral('2')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertSame(3, (new TextResults())->substring(new Invocation([Domain::string(6, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::integer(), Domain::integer()], [$text, new NumberLiteral('2'), new NumberLiteral('3')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }
}
