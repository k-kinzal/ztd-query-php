<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\DeclarationConstraints;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DeclarationConstraints::class)]
#[Small]
final class DeclarationConstraintsTest extends TestCase
{
    public function testProblemDistinguishesFixedDeclarationsFromQuerySuppliedColumns(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $key = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Primary, [new M\Table\Key\ColumnPart(new Name('a'))]);
        $rules = new DeclarationConstraints();
        self::assertNull($rules->problem([$column, $key], null));
        self::assertInstanceOf(M\Table\Problem\DuplicateColumn::class, $rules->problem([$column, $column], null));
        self::assertInstanceOf(M\Table\Problem\MultiplePrimaryKeys::class, $rules->problem([$column, $key, $key], null));
        self::assertInstanceOf(M\Table\Problem\UnknownKeyColumn::class, $rules->problem([$key], null));
        $query = new M\Table\TableQuery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]));
        self::assertNull($rules->problem([$key], $query));
    }

    public function testPartsKeepIndexOccurrencesDistinctFromForeignKeyReferences(): void
    {
        $parts = [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('A'))];
        $rules = new DeclarationConstraints();
        self::assertInstanceOf(M\Table\Problem\DuplicateColumn::class, $rules->parts($parts, ['a' => true], true, true));
        self::assertNull($rules->parts($parts, ['a' => true], true, false));
        self::assertInstanceOf(M\Table\Problem\UnknownKeyColumn::class, $rules->parts($parts, [], true, true));
        self::assertNull($rules->parts([$parts[0]], [], false, true));
    }
}
