<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Preparation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\QuantifiedAttributes;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(QuantifiedAttributes::class)]
#[Small]
final class QuantifiedAttributesTest extends TestCase
{
    public function testNullableSeparatesConstantNonnullableSourcesFromStoredColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $table = $semantics->analyze('CREATE TABLE t(i INT)');
        $constant = $semantics->analyze('SELECT 7 FROM t', [$table]);
        $column = $semantics->analyze('SELECT i FROM t', [$table]);
        self::assertInstanceOf(QueryRoot::class, $constant);
        self::assertInstanceOf(QueryRoot::class, $column);
        self::assertTrue((new QuantifiedAttributes())->nullable($constant->query));
        self::assertFalse((new QuantifiedAttributes())->nullable($column->query));
    }

    public function testFixedExcludesNestedVariableAndPerReadEffects(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1=1, @v=1, RAND(1)<0');
        $rules = new QuantifiedAttributes();
        self::assertNotNull($operation->field(0)->expression);
        self::assertNotNull($operation->field(1)->expression);
        self::assertNotNull($operation->field(2)->expression);
        self::assertTrue($rules->fixed($operation->field(0)->expression));
        self::assertFalse($rules->fixed($operation->field(1)->expression));
        self::assertFalse($rules->fixed($operation->field(2)->expression));
    }

    public function testNullablePreservesOuterFlagsForNullOutputsAndRuntimeFilters(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $table = $semantics->analyze('CREATE TABLE t(i INT)');
        $operation = $semantics->analyze('SELECT 7 <= ANY(SELECT NULL FROM t),7 <= ANY(SELECT 7 FROM t WHERE @v),7 = ALL(SELECT 7 FROM t),7 <= ANY(SELECT 7 FROM t)', [$table]);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(1)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(2)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(3)->nullability);
    }
    public function testRuntimeFilterRetainsTheBoundModernNullAttribute(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 7 <= ANY(SELECT 7 WHERE @v),7 <= ANY(SELECT 7 FROM DUAL WHERE FALSE),7 <= ANY(SELECT NULL WHERE @v)');
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(2)->nullability);
    }

    public function testLegacyRetainsTheMySql56TablelessNullOperandAttribute(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT 7 <= ANY(SELECT NULL FROM DUAL WHERE FALSE),7 <= ANY(SELECT IF(1,NULL,7) FROM DUAL WHERE @v),7 <= ANY(SELECT 7 FROM DUAL WHERE FALSE)');
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(2)->nullability);
    }

    public function testModernRetainsTheMySql80InnerAttributeForNullableOperands(): void
    {
        $old = new Semantics(Dialect::MySql, 'mysql-8.0.44');
        $later = new Semantics(Dialect::MySql, 'mysql-8.1.0');
        $table = $old->analyze('CREATE TABLE t(i INT NOT NULL)');
        $sql = 'SELECT NULL <= ANY(SELECT i FROM t),NULL = ALL(SELECT i FROM t),NULL IN(SELECT i FROM t)';
        $operation = $old->analyze($sql, [$table]);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(2)->nullability);
        self::assertSame(Nullability::Nullable, $later->analyze($sql, [$later->analyze('CREATE TABLE t(i INT NOT NULL)')])->field(0)->nullability);
    }

}
