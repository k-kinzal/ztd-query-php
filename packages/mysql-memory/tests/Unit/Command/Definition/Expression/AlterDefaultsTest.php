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
    /**
     * @return iterable<array{M\Routine\Program\Write\DeferredAlterTable}>
     */
    public static function providerDefinitions(): iterable
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        yield [new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('t')), [new M\Routine\Program\Write\DeferredDefaultSetting(new M\Name\ColumnName(new Name('a')), $row)])];
    }

    #[DataProvider('providerDefinitions')]
    public function testFactsBindTheSameTargetAfterItsDeclarationAppears(M\Routine\Program\Write\DeferredAlterTable $definition): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $preparation = new AlterDefaults();
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $preparation->facts($definition, $session, 'd')->relation($definition)->table);
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated invalid input';
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $preparation->facts($definition, $session, 'd')->relation($definition)->table);
        self::assertSame('unrelated invalid input', $session->text);
    }

    #[DataProvider('providerDefinitions')]
    public function testCheckReportsTheRowDefaultAfterOpeningItsTable(M\Routine\Program\Write\DeferredAlterTable $definition): void
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
