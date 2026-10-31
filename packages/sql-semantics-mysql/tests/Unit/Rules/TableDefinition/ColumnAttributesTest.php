<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute;

#[CoversClass(ColumnAttributes::class)]
#[Small]
final class ColumnAttributesTest extends TestCase
{
    public function testProblemPreservesTheColumnAndTargetWhilePrioritizingTheEffectiveDefault(): void
    {
        $request = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE d.t(c BIT SRID 1 DEFAULT CURRENT_TIMESTAMP)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $request);
        $column = $request->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition::class, $column);
        $problem = (new ColumnAttributes())->problem($column, $request->name);
        self::assertNotNull($problem);
        self::assertSame($column->name->column, $problem->column);
        self::assertSame($request->name, $problem->scope);
        self::assertSame('DEFAULT', $problem->attribute);
    }

    public function testNoticesRetainGrammarWarningsBeforeTheModernColumnCondition(): void
    {
        $request = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(c BIT SRID 1, b INT(3))');
        $warnings = $request->warnings();
        self::assertCount(2, $warnings);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::class, $warnings[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warnings[1]);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization, $warnings[1]->phase);
        self::assertTrue($warnings[1]->aborts);
    }

    public function testEnforcementRequiresAnUninterruptedCheckChain(): void
    {
        $rules = new ColumnAttributes();
        $check = new \SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint(new \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral(true));
        $yes = new \SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute(true);
        $no = new \SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute(false);
        $comment = new \SqlSemantics\Platform\MySql\Statement\Table\Column\CommentAttribute(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('x'));
        self::assertNull($rules->enforcement([$check, $yes, $no]));
        self::assertSame(0, $rules->enforcement([$yes]));
        self::assertSame(2, $rules->enforcement([$check, $comment, $no]));
        $this->expectExceptionMessage('Column enforcement follows a CHECK constraint or its enforcement chain.');
        new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), [$yes]);
    }

    public function testGeneratedNamesTheFirstConflictingAttributeBeforeTheType(): void
    {
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Serial);
        $attributes = [new \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0'))];

        self::assertSame('DEFAULT', (new ColumnAttributes())->generated($type, $attributes));
        self::assertSame('SERIAL', (new ColumnAttributes())->generated($type, []));
    }

    public function testAttributeRecognizesAutoIncrementTypeFamilies(): void
    {
        $attribute = new \SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute(\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::AutoIncrement);
        $rules = new ColumnAttributes();
        self::assertNull($rules->attribute(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Boolean), $attribute));
        self::assertNull($rules->attribute(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Serial), $attribute));
        self::assertNull($rules->attribute(new \SqlSemantics\Platform\MySql\Statement\Type\Floating(\SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind::Float), $attribute));
        self::assertSame('AUTO_INCREMENT', $rules->attribute(new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit), $attribute));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerAttributes(): iterable
    {
        yield 'replaced clock default' => ['BIT DEFAULT CURRENT_TIMESTAMP DEFAULT 0', []];
        yield 'replaced clock precision' => ['DATETIME(3) DEFAULT CURRENT_TIMESTAMP(2) DEFAULT CURRENT_TIMESTAMP(3)', []];
        yield 'replaced update precision' => ['DATETIME(3) ON UPDATE CURRENT_TIMESTAMP(2) ON UPDATE CURRENT_TIMESTAMP(3)', []];
        yield 'nonspatial SRID' => ['INT SRID 4326', ['SRID']];
        yield 'generated nonspatial SRID' => ['INT AS (1) SRID 4326', ['SRID']];
        yield 'default precedes SRID' => ['BIT SRID 1 DEFAULT CURRENT_TIMESTAMP', ['DEFAULT']];
        yield 'update precedes SRID' => ['BIT SRID 1 ON UPDATE CURRENT_TIMESTAMP', ['ON UPDATE']];
        yield 'spatial SRID' => ['POINT SRID 4326', []];
        yield 'integer default' => ['INT DEFAULT CURRENT_TIMESTAMP', ['DEFAULT']];
        yield 'date default' => ['DATE DEFAULT CURRENT_TIMESTAMP', ['DEFAULT']];
        yield 'integer on update' => ['INT ON UPDATE CURRENT_TIMESTAMP', ['ON UPDATE']];
        yield 'default precision' => ['TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP', ['DEFAULT']];
        yield 'update precision' => ['DATETIME(3) ON UPDATE CURRENT_TIMESTAMP(2)', ['ON UPDATE']];
        yield 'matching precision' => ['DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)', []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerAttributes')]
    public function testIncompatibleResolvesTheAttributeAgainstItsColumnType(string $definition, array $expected): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = 'CREATE TABLE t(c ' . $definition . ')';
        $operation = $semantics->analyze($sql);
        $problems = array_values(array_filter($operation->diagnostics(), static fn ($problem): bool => $problem instanceof InvalidColumnAttribute));

        self::assertSame($expected, array_map(static fn ($problem): string => $problem->attribute, $problems));
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($operation->toString()));
    }
}
