<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Names;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Names::class)]
#[Medium]
final class NamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidNames(): iterable
    {
        foreach (['PRIMARY', 'primary', 'Primary', '', 'a '] as $value) {
            yield 'name:' . $value => [$value];
        }
    }

    #[DataProvider('providerInvalidNames')]
    public function testProblemPreservesTheRejectedOccurrence(string $value): void
    {
        $name = new Name($value);
        $problem = (new Names())->problem($name);
        self::assertInstanceOf(M\Table\Problem\Creation\InvalidIndexName::class, $problem);
        self::assertSame($name, $problem->name);
    }

    public function testProblemAcceptsAbsentAndUnreservedNames(): void
    {
        self::assertNull((new Names())->problem(null));
        self::assertNull((new Names())->problem(new Name('a.')));
        self::assertNull((new Names())->problem(new Name('あ')));
        self::assertNull((new Names())->problem(new Name(' PRIMARY')));
    }

    public function testDefinitionUsesTheIndexNameBeforeTheConstraintNameAndIgnoresPrimaryNames(): void
    {
        $part = new M\Table\Key\ColumnPart(new Name('a'));
        $name = new M\Name\ColumnName(new Name('PRIMARY'));
        $constraint = new M\Table\Key\ConstraintName($name);
        self::assertNull((new Names())->definition(new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Primary, [$part], new M\Name\ColumnName(new Name('a ')), constraint: $constraint)));
        self::assertNull((new Names())->definition(new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Unique, [$part], new M\Name\ColumnName(new Name('valid')), constraint: $constraint)));
        self::assertSame($name->column, (new Names())->definition(new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Unique, [$part], constraint: $constraint))?->name);
    }

    #[DataProvider('providerInvalidNames')]
    public function testOrdinaryIndexCannotRetainAnInvalidName(string $value): void
    {
        $this->expectExceptionMessage('An ordinary index request requires a valid name and key operands compatible with its kind.');
        new M\Table\CreateIndex(new Name($value), new QualifiedName(new Name('t')), [new M\Table\Key\ColumnPart(new Name('a'))]);
    }

    #[DataProvider('providerInvalidNames')]
    public function testOrdinaryTableCannotRetainAnInvalidIndexName(string $value): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\ColumnPart(new Name('a'))], new M\Name\ColumnName(new Name($value)));
        $this->expectExceptionMessage('An index with a fixed name or operand condition belongs to a deferred creation instruction.');
        new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column, $index]);
    }

    #[DataProvider('providerInvalidNames')]
    public function testOrdinaryAlterCannotRetainAnInvalidIndexName(string $value): void
    {
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [new M\Table\Key\ColumnPart(new Name('a'))], new M\Name\ColumnName(new Name($value)));
        $this->expectExceptionMessage('An ordinary ALTER statement excludes actions with an intrinsic invocation-time condition.');
        new M\Alter\AlterTable(new QualifiedName(new Name('t')), [new M\Alter\Command\AddConstraint($index)]);
    }
}
