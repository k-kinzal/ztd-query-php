<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\AlterDefaults;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(AlterDefaults::class)]
#[Medium]
final class AlterDefaultsTest extends TestCase
{
    public function testFunctionsRejectsTheDeclaredDefaultWithoutLookingUpItsTarget(): void
    {
        $call = new M\Call\FunctionCall(new Name('f'), [], new Name('other'));
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('v')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), [new M\Table\Column\DefaultExpression($call)]));
        $this->expectExceptionCode(3770);
        $this->expectExceptionMessage("Default value expression of column 'v' contains a disallowed function: `other`.`f`.");

        (new AlterDefaults())->functions([new M\Alter\Column\AddColumn($column)], \SqlSemantics\Contract\GrammarRelease::MySql847);
    }

    public function testCallsCompletesArgumentsInTheirWrittenOrder(): void
    {
        $first = new M\Call\FunctionCall(new Name('first'));
        $second = new M\Call\FunctionCall(new Name('second'));
        $outer = new M\Call\FunctionCall(new Name('outer'), [new M\Call\CallArgument($first), new M\Call\CallArgument($second)]);

        self::assertSame([$first, $second, $outer], (new AlterDefaults())->calls($outer));
    }

    /**
     * @return iterable<array{M\Alter\AlterTable}>
     */
    public static function providerDefinitions(): iterable
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        yield [new M\Alter\AlterTable(new QualifiedName(new Name('t')), [new M\Routine\Program\Write\DeferredDefaultSetting(new M\Name\ColumnName(new Name('a')), $row)])];
    }

    #[DataProvider('providerDefinitions')]
    public function testBoundResolvesFreshOccurrencesAfterTheDeclarationAppears(M\Alter\AlterTable $definition): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $preparation = new AlterDefaults();
        $absent = $preparation->bound($definition, $session, 'd');
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $absent->relationInformation()->table);
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated invalid input';
        $present = $preparation->bound($definition, $session, 'd');
        self::assertNotSame($absent, $present);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $present->relationInformation()->table);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $absent->relationInformation()->table);
        self::assertSame('unrelated invalid input', $session->text);
    }

    #[DataProvider('providerDefinitions')]
    public function testCheckReportsTheRowDefaultAfterOpeningItsTable(M\Alter\AlterTable $definition): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $session->text = 'unrelated invalid input';
        $this->expectExceptionCode(3771);
        $this->expectExceptionMessage("Default value expression of column 'a' cannot refer to a row value.");
        (new AlterDefaults())->check($definition, $table, $session, 'd');
    }
}
