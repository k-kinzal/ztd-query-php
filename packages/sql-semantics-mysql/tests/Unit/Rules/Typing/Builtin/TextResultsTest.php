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
    public function testRulesSumConcatenationsAndCountSeparators(): void
    {
        $rules = (new TextResults())->rules();

        self::assertSame(5, $rules['CONCAT'](new Invocation([Domain::string(2, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))?->length);
        self::assertSame(6, $rules['CONCAT_WS'](new Invocation([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::string(2, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))?->length);
        self::assertSame(3, $rules['REPEAT'](new Invocation([Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), Domain::integer()], [new StringLiteral(['a']), new NumberLiteral('3')], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([]))))?->length);
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
