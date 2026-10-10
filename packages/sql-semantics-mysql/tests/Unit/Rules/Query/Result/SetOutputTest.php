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
use SqlSemantics\Statement\Query;
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
        self::assertInstanceOf(Query::class, $operation->statement);
        $reported = $operation->field(0)->type;
        $stored = $operation->facts->query($operation->statement)->shape->slots[0]->type;
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
        self::assertInstanceOf(Query::class, $operation->statement);
        $returned = $operation->field(0)->type;
        $stored = $operation->facts->query($operation->statement)->shape->slots[0]->type;
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
        self::assertInstanceOf(Query::class, $distinct->statement);
        self::assertInstanceOf(Query::class, $ordered->statement);

        self::assertSame($distinct->facts->query($distinct->statement), $distinct->facts->output);
        self::assertSame($ordered->facts->query($ordered->statement), $ordered->facts->output);
    }

    public function testBodyUnwrapsALimitButRetainsGlobalOrdering(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $limited = $semantics->analyze("SELECT 'x' AS q UNION ALL SELECT 'y' LIMIT 1")->statement;
        $ordered = $semantics->analyze("SELECT 'x' AS q UNION ALL SELECT 'y' ORDER BY q")->statement;
        self::assertInstanceOf(Query::class, $limited);
        self::assertInstanceOf(Query::class, $ordered);

        self::assertInstanceOf(SetOperation::class, (new SetOutput())->body($limited));
        self::assertSame($ordered, (new SetOutput())->body($ordered));
    }

    public function testStreamedRequiresAllOfTheSetChainToKeepDuplicates(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $all = $semantics->analyze('SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3')->statement;
        $mixed = $semantics->analyze('SELECT 1 UNION SELECT 2 UNION ALL SELECT 3')->statement;
        self::assertInstanceOf(Query::class, $all);
        self::assertInstanceOf(Query::class, $mixed);

        self::assertTrue((new SetOutput())->streamed($all));
        self::assertFalse((new SetOutput())->streamed($mixed));
    }

    public function testDomainsRetainOriginalOperandBoundsBeforeStorageAllocation(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SELECT GROUP_CONCAT(CONVERT('x' USING latin1)) UNION ALL SELECT _latin1'y'");
        self::assertInstanceOf(Query::class, $operation->statement);
        $derivation = new Derivation($operation->context);
        $derivation->query($operation->statement, $derivation->environment());
        $domains = (new SetOutput())->domains($operation->statement, 0, $derivation);

        self::assertNotNull($domains);
        self::assertCount(2, $domains);
        self::assertSame([1024, 1], [$domains[0]->length, $domains[1]->length]);
    }
}
