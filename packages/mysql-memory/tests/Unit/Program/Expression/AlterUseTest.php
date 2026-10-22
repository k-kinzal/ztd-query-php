<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\AlterUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(AlterUse::class)]
#[Medium]
final class AlterUseTest extends TestCase
{
    public function testHeaderPreservesPartitionContextualizationBeforeValidationOrTableLookup(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('missing')), [
            new M\Alter\Modifier\ValidationOption(true),
            new M\Alter\Partition\PartitionBy(new M\Partition\PartitionClause(new M\Partition\Method\HashMethod(false, new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])), null, null, [])),
        ]);
        $session->text = 'unrelated invalid input';

        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage('Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed');
        (new AlterUse())->header($definition, $session);
    }

    public function testHeaderPreservesMissingRangeDefinitionsBeforeValidation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('missing')), [
            new M\Alter\Modifier\ValidationOption(true),
            new M\Alter\Partition\PartitionBy(new M\Partition\PartitionClause(new M\Partition\Method\ExpressionMethod(M\Partition\Method\PartitionKind::Range, new M\Name\ColumnUse(new Name('a'))), null, null, [])),
        ]);
        $session->text = 'unrelated invalid input';

        $this->expectExceptionCode(1492);
        $this->expectExceptionMessage('For RANGE partitions each partition must be defined');
        (new AlterUse())->header($definition, $session);
    }

    public function testRaiseRejectsACreationTransactionBeforeOpeningTheAlterTarget(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('t')), [new M\Alter\Command\SetTableOptions([new M\Table\Option\StartTransaction()])]);
        $session->text = 'unrelated invalid input';
        $this->expectExceptionMessage('START TRANSACTION clause cannot be used with ALTER TABLE command.');
        (new AlterUse())->raise($definition, $session);
    }

    /**
     * @return iterable<array{M\Routine\Program\Write\DeferredAlterTable}>
     */
    public static function providerDefinitions(): iterable
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])));
        yield [new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('t')), [new M\Alter\Column\ChangeColumn(null, $column)])];
    }

    #[DataProvider('providerDefinitions')]
    public function testHeaderResolvesThePreparedDatabase(M\Routine\Program\Write\DeferredAlterTable $definition): void
    {
        self::assertSame('prepared_db', (new AlterUse())->header($definition, (new Instance('5.7.44'))->connect(), 'prepared_db'));
    }

    #[DataProvider('providerDefinitions')]
    public function testRaiseChecksTheOriginalColumnBeforeItsGenerationExpression(M\Routine\Program\Write\DeferredAlterTable $definition): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(g INT)');
        $session->text = 'unrelated invalid input';
        $this->expectExceptionMessage("'Changing the STORED status' is not supported for generated columns.");
        (new AlterUse())->raise($definition, $session);
    }
}
