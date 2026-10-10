<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\ControlResults;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(ControlResults::class)]
#[Medium]
final class ControlResultsTest extends TestCase
{
    public function testNullableRetainsTemporalEvaluationWhileReportingAStringField(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $domain = $semantics->analyze("SELECT NULLIF(CAST('12:01:02.345' AS TIME(3)),NULL)")->field(0)->type;

        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $domain);
        self::assertInstanceOf(Domain::class, $domain->descriptor);
        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Time, Field::VarString, 3, Domain::NOT_FIXED, 'latin1'], [$domain->descriptor->kind, $domain->descriptor->field, $domain->descriptor->decimals, $domain->descriptor->displayDecimals, $domain->descriptor->collation->charset->name]);
    }

    public function testNullableNormalizesAComputedBlobAndPreservesLegacyNullMetadata(): void
    {
        $settings = new Settings(Collation::known('utf8mb4_general_ci'));
        $rule = new ControlResults();
        $modern = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $legacy = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]));
        $blob = $rule->nullable(new Invocation([Domain::string(68000, $settings->connection, Field::MediumBlob)], [], $settings, $modern));
        $null = $rule->nullable(new Invocation([Domain::null()], [], $settings, $legacy));

        self::assertSame([17000, 68000], [$blob->length, $blob->metadataLength()]);
        self::assertSame([Field::VarString, 0, 0], [$null->field, $null->length, $null->decimals]);
    }

    public function testBranchesUseTheFirstKnownBranchBeforeAggregatingTheResults(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([], session: new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: []));
        $query = $semantics->analyze("SELECT COALESCE(@v,1,'a'),COALESCE(@v,'a',1),COALESCE(@v,NULL,1)", $context);

        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::string(21, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)), $query->field(0)->type);
        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::string(16383, Collation::known('utf8mb4_0900_ai_ci'))), $query->field(1)->type);
        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::string(65535, Collation::binary())), $query->field(2)->type);
    }
    public function testBranchesInferAbsentNumericVariablesWithoutAllocatingThem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([], session: new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: []));
        $query = $semantics->analyze('SELECT COALESCE(@v,0), COALESCE(@v,1.2), IFNULL(@v,0), IF(1,@v,0), @v', $context);

        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::integer(Field::LongLong, 21)), $query->field(0)->type);
        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::decimal(65, 30)), $query->field(1)->type);
        self::assertEquals($query->field(0)->type, $query->field(2)->type);
        self::assertEquals($query->field(0)->type, $query->field(3)->type);
        self::assertEquals(new \SqlSemantics\Statement\Type\Known(Domain::string(65532, Collation::binary())), $query->field(4)->type);
    }

    public function testRulesSettleTheBranches(): void
    {
        $rules = (new ControlResults())->rules();

        self::assertEquals(Domain::decimal(2, 1), $rules['IF'](new Invocation([Domain::integer(Field::LongLong, 2), Domain::integer(Field::LongLong, 2), Domain::decimal(2, 1)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), $rules['IFNULL'](new Invocation([Domain::null(), Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::integer(Field::LongLong, 2), $rules['NULLIF'](new Invocation([Domain::integer(Field::LongLong, 2), Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }
}
