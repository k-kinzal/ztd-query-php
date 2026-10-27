<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Result\SetOutput;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(SetOutput::class)]
#[Medium]
final class SetOutputTest extends TestCase
{
    public function testReturnedPreservesFractionalPrecisionOfTemporalStringFields(): void
    {
        $rules = new SetOutput();
        $stored = Domain::string(23, Collation::known('utf8mb4_general_ci'));
        $temporal = new Domain(Kind::DateTime, Field::VarString, 23, 3);

        self::assertEquals(new Domain(Kind::String, Field::VarString, 23, 3, false, $stored->collation, [], $stored->coercibility), $rules->returned($stored, $temporal));
        self::assertEquals($stored, $rules->returned($stored, $stored));
        self::assertNull($rules->returned($stored, Domain::integer()));
        self::assertNull($rules->returned($stored, null));
    }

    public function testDeriveSeparatesReturnedStringsFromMaterializedQueryColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $sql = "SELECT GROUP_CONCAT(CONVERT('x' USING latin1)) AS q UNION ALL SELECT _latin1'y'";
        $operation = $semantics->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        $reported = $operation->field(0)->type;
        $stored = $operation->query->queryInformation()->shape->slots[0]->type;
        $scalar = $semantics->analyze("SELECT ({$sql} LIMIT 1)")->field(0)->type;

        self::assertInstanceOf(Known::class, $reported);
        self::assertInstanceOf(Known::class, $stored);
        self::assertInstanceOf(Known::class, $scalar);
        self::assertInstanceOf(Domain::class, $reported->descriptor);
        self::assertInstanceOf(Domain::class, $stored->descriptor);
        self::assertInstanceOf(Domain::class, $scalar->descriptor);
        self::assertSame([Field::VarString, 1024, Domain::NOT_FIXED], [$reported->descriptor->field, $reported->descriptor->length, $reported->descriptor->decimals]);
        self::assertSame([Field::Blob, 1024, 65535], [$stored->descriptor->field, $stored->descriptor->length, $stored->descriptor->capacity]);
        self::assertSame([Field::Blob, 65535], [$scalar->descriptor->field, $scalar->descriptor->length]);
    }

    public function testDeriveKeepsTemporalPrecisionOnlyForTheStreamedResult(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $table = $semantics->analyze('CREATE TABLE t (a DATETIME(3))');
        $operation = $semantics->analyze('SELECT NULLIF(a,NULL) x FROM t UNION ALL SELECT NULLIF(a,NULL) FROM t', [$table]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        $returned = $operation->field(0)->type;
        $stored = $operation->query->queryInformation()->shape->slots[0]->type;
        self::assertInstanceOf(Known::class, $returned);
        self::assertInstanceOf(Known::class, $stored);
        self::assertInstanceOf(Domain::class, $returned->descriptor);
        self::assertInstanceOf(Domain::class, $stored->descriptor);

        self::assertSame([Kind::String, Field::VarString, 3], [$returned->descriptor->kind, $returned->descriptor->field, $returned->descriptor->decimals]);
        self::assertSame([Kind::String, Field::VarString, 0], [$stored->descriptor->kind, $stored->descriptor->field, $stored->descriptor->decimals]);
    }

    public function testDeriveKeepsDistinctAndOrderedSetsMaterialized(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $distinct = $semantics->analyze("SELECT 'x' AS q UNION SELECT 'y'");
        $ordered = $semantics->analyze("SELECT 'x' AS q UNION ALL SELECT 'y' ORDER BY q");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $distinct);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $ordered);

        self::assertSame($distinct->query->queryInformation(), $distinct->output());
        self::assertSame($ordered->query->queryInformation(), $ordered->output());
    }

    public function testBodyUnwrapsALimitButRetainsGlobalOrdering(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $limited = $semantics->analyze("SELECT 'x' AS q UNION ALL SELECT 'y' LIMIT 1");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $limited);
        $limited = $limited->query;
        $ordered = $semantics->analyze("SELECT 'x' AS q UNION ALL SELECT 'y' ORDER BY q");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $ordered);
        $ordered = $ordered->query;

        self::assertInstanceOf(SetOperation::class, (new SetOutput())->body($limited));
        self::assertSame($ordered, (new SetOutput())->body($ordered));
    }

    public function testStreamedRequiresAllOfTheSetChainToKeepDuplicates(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $all = $semantics->analyze('SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $all);
        $all = $all->query;
        $mixed = $semantics->analyze('SELECT 1 UNION SELECT 2 UNION ALL SELECT 3');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $mixed);
        $mixed = $mixed->query;

        self::assertTrue((new SetOutput())->streamed($all));
        self::assertFalse((new SetOutput())->streamed($mixed));
    }

    public function testDomainsRetainOriginalOperandBoundsBeforeStorageAllocation(): void
    {
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Occurrences())->copy((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SELECT GROUP_CONCAT(CONVERT('x' USING latin1)) UNION ALL SELECT _latin1'y'"));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $derivation->query($operation->query, $derivation->environment());
        $domains = (new SetOutput())->domains($operation->query, 0, $derivation);

        self::assertNotNull($domains);
        self::assertCount(2, $domains);
        self::assertSame([1024, 1], [$domains[0]->length, $domains[1]->length]);
    }
    public function testFieldsRetainsTheInterpretedExpressionOfEachOperand(): void
    {
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Occurrences())->copy((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 2147483648 UNION ALL SELECT 1.25'));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $derivation->query($operation->query, $derivation->environment());
        $fields = (new SetOutput())->fields($operation->query, 0, $derivation);

        self::assertCount(2, $fields);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, $fields[0]?->expression);
        self::assertSame('2147483648', $fields[0]->expression->text);
    }

}
