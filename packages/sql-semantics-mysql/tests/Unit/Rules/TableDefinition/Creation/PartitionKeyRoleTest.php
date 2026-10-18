<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\PartitionKeyRole;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(PartitionKeyRole::class)]
#[Small]
final class PartitionKeyRoleTest extends TestCase
{
    public function testMissingKeepsAnImplicitKeyWithoutASourceOutsideOrdinaryDeclarations(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $partition = new M\Partition\PartitionClause(new M\Partition\Method\KeyMethod(false, null, []), null, null, []);
        self::assertTrue((new PartitionKeyRole())->missing([$column], $partition, null));
        self::assertFalse((new PartitionKeyRole())->missing([$column], null, null));
        $this->expectExceptionMessage('An implicit partition key absent from the declaration belongs to a deferred creation instruction.');
        new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column], partitioning: $partition);
    }

    public function testColumnRecognizesPrimaryAndNonnullableUniqueKeys(): void
    {
        $type = new M\Type\Integral(M\Type\Kind\IntegralKind::Int);
        $name = new M\Name\ColumnName(new Name('a'));
        $unique = new M\Table\Column\KeywordAttribute(M\Table\Column\Kind\ColumnKeyword::Unique);
        $notNull = new M\Table\Column\KeywordAttribute(M\Table\Column\Kind\ColumnKeyword::NotNull);
        $rules = new PartitionKeyRole();
        self::assertFalse($rules->column(new M\Table\Column\ColumnDefinition($name, new M\Table\Column\OrdinaryColumn($type, [$unique]))));
        self::assertTrue($rules->column(new M\Table\Column\ColumnDefinition($name, new M\Table\Column\OrdinaryColumn($type, [$unique, $notNull]))));
        self::assertTrue($rules->column(new M\Table\Column\ColumnDefinition($name, new M\Table\Column\OrdinaryColumn($type, [new M\Table\Column\KeywordAttribute(M\Table\Column\Kind\ColumnKeyword::PrimaryKey)]))));
    }

    public function testCandidateLeavesUnresolvedAndSessionDependentColumnsToBinding(): void
    {
        $key = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Unique, [new M\Table\Key\ColumnPart(new Name('A'))]);
        $rules = new PartitionKeyRole();
        self::assertFalse($rules->candidate($key, ['a' => Nullability::Nullable]));
        self::assertTrue($rules->candidate($key, ['a' => Nullability::NotNull]));
        self::assertTrue($rules->candidate($key, ['a' => Nullability::Dependent]));
        self::assertTrue($rules->candidate($key, []));
    }
}
