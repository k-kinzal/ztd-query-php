<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column\ColumnRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;

#[CoversClass(ColumnRule::class)]
#[Medium]
final class ColumnRuleTest extends TestCase
{
    public function testSpecificationRefusesAnOutOfRangeTypeWithItsColumnIdentity(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE TABLE d.t(c BIT(65))');
            self::fail('The bit width exceeds 64.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize::class, $failure->problem);
            self::assertSame('c', $failure->problem->name);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerGenerationOperands(): iterable
    {
        foreach (['USER()', '@v', '@@autocommit', '(SELECT 1)', 'ROW(1, 2)', '?', 'f1()', 'd.f1()'] as $expression) {
            foreach (['CREATE TABLE t (a INT, g INT GENERATED ALWAYS AS (%s) STORED)', 'ALTER TABLE t ADD g INT AS (%s)', 'ALTER TABLE t MODIFY g INT AS (%s)', 'ALTER TABLE t CHANGE a g INT AS (%s)', 'CREATE PROCEDURE p() CREATE TABLE t (g INT AS (%s))'] as $form) {
                $sql = sprintf($form, $expression);
                yield $sql => [$sql];
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerGenerationOperands')]
    public function testSpecificationRefusesEveryGenerationOperandOnceTheStatementIsRead(string $sql): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('A generation expression excludes the operand.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand::class, $failure->problem);
            self::assertSame('g', $failure->problem->column->value);
            self::assertFalse($failure->aborts);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDefaultOperands(): iterable
    {
        yield 'variable' => ['CREATE TABLE t (a INT DEFAULT (@v))', "The default of column 'a' excludes a session variable."];
        yield 'subquery' => ['ALTER TABLE t ADD a INT DEFAULT ((SELECT 1))', "The default of column 'a' excludes a subquery."];
        yield 'state function' => ['CREATE TABLE t (a INT DEFAULT (ROW_COUNT()))', "The default of column 'a' excludes function row_count."];
        yield 'stored function' => ['CREATE TABLE t (a INT DEFAULT (f1()))', "The default of column 'a' excludes function `f1`."];
        yield 'body' => ['CREATE PROCEDURE p() CREATE TABLE t (a INT DEFAULT (@v))', "The default of column 'a' excludes a session variable."];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDefaultOperands')]
    public function testSpecificationRefusesAnExpressionDefaultOperand(string $sql, string $message): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('An expression default excludes the operand.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertSame($message, $failure->problem->message());
            self::assertFalse($failure->aborts);
        }
    }

    public function testSpecificationAcceptsTheClockInAnExpressionDefault(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT DEFAULT (RAND()), b DATETIME DEFAULT (NOW()))');
        self::assertStringContainsString('RAND()', $statement->toString());
    }

    public function testSpecificationRefusesALegacyStoredFunctionWhileReading(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE TABLE t (g INT AS (f1()))');
            self::fail('MySQL 5.7 refuses a stored function while reading.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand::class, $failure->problem);
            self::assertTrue($failure->aborts);
        }
    }

    public function testDeclaredHoldsABoundRefusedOnceTheStatementIsRead(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT(300))')->find('type')[0];
        [$type, $sized] = (new ColumnRule($lowering))->declared($node, new \SqlSemantics\Statement\Identifier\Name('a'));
        self::assertNull($type);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize::class, $sized?->problem);
    }

    public function testSpecificationRefusesTheExpressionBeforeTheTypeBoundOfItsColumn(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a CHAR(300) DEFAULT (@v), g INT(300) AS (@v))');
            self::fail('The expression default excludes the variable.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertSame("The default of column 'a' excludes a session variable.", $failure->problem->message());
        }
    }

    public function testRefusedRaisesTheOperandWithTheColumnTable(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $lowering->columnTable = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'));
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1) STORED)')->find('field_def')[0];
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Generated column 'a' excludes a session variable.");
        (new ColumnRule($lowering))->refused(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind::Variable), $node);
    }

    public function testSpecificationLowersAGeneratedColumn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1) STORED)')->find('field_def')[0];

        self::assertInstanceOf(GeneratedColumn::class, (new ColumnRule($lowering))->specification($node));
    }

    public function testOrdinaryLowersALegacyTypeAndAttributes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT NOT NULL)')->find('type')[0];
        $specification = (new ColumnRule($lowering))->specification($node, $platform->parser($profile)->parse('CREATE TABLE t (a INT NOT NULL)')->find('opt_attribute')[0]);

        self::assertInstanceOf(OrdinaryColumn::class, $specification);
        self::assertCount(1, $specification->attributes);
    }

    public function testSpecificationRetainsATypeContradictionWithItsColumnIdentity(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE d.t MODIFY c BIT AUTO_INCREMENT');
        $conditions = array_values(array_filter($operation->diagnostics(), static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute));
        self::assertCount(1, $conditions);
        self::assertSame('c', $conditions[0]->column->value);
        self::assertSame('d', $conditions[0]->scope?->schema?->value);
        self::assertSame('AUTO_INCREMENT', $conditions[0]->attribute);
        self::assertSame('ALTER TABLE d.t MODIFY c BIT AUTO_INCREMENT', $operation->toString());
    }

    public function testAttributesLowersTheListInOrder(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT NULL DEFAULT 1 COMMENT \'c\')')->find('opt_column_attribute_list')[0];

        self::assertCount(3, (new ColumnRule($lowering))->attributes($node));
    }

    public function testSpecificationRetainsAnAttributeOfAGeneratedColumn(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT, g INT AS (a) NOT NULL DEFAULT 1)')->find('field_def')[1];

        $specification = (new ColumnRule($lowering))->specification($node);
        self::assertInstanceOf(GeneratedColumn::class, $specification);
        self::assertCount(2, $specification->attributes);
    }

    public function testSpecificationReportsTheFirstIncompatibleAttribute(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        $statement = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a) UNIQUE COLUMN_FORMAT FIXED ON UPDATE CURRENT_TIMESTAMP)');
        $conditions = array_values(array_filter($statement->diagnostics(), static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\InvalidAttribute));
        self::assertSame('Incorrect usage of COLUMN_FORMAT and generated column', $conditions[0]->message());
    }

    public function testSpecificationReportsTheTypeSerial(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD g SERIAL AS (1)');
        $conditions = array_values(array_filter($statement->diagnostics(), static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\InvalidAttribute));
        self::assertSame('Incorrect usage of SERIAL and generated column', $conditions[0]->message());
    }

    public function testSpecificationAcceptsTheOtherAttributes(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, g INT AS (a) VIRTUAL NOT NULL UNIQUE KEY COMMENT \'c\' INVISIBLE)');

        self::assertSame([], $create->diagnostics());
    }

    public function testAlwaysAcceptsTheOptionalWords(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT GENERATED ALWAYS AS (1))')->find('opt_generated_always')[0];
        (new ColumnRule($lowering))->always($node);

        $this->expectExceptionMessage('No semantic rule is implemented for: opt_stored_attribute:');

        (new ColumnRule($lowering))->always($platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1))')->find('opt_stored_attribute')[0]);
    }

    public function testStorageLowersVirtualAndStored(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1) VIRTUAL)')->find('opt_stored_attribute')[0];

        self::assertSame(GeneratedStorage::Virtual, (new ColumnRule($lowering))->storage($node));
    }

    public function testExpressionLowersAGeneratedColumnFunction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (a INT AS (1))')->find('generated_column_func')[0];

        self::assertInstanceOf(NumberLiteral::class, (new ColumnRule($lowering))->expression($node));
    }

    public function testParseRefusesAnInternalEntryInsteadOfPublishingAStatement(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('PARSE_GCOL_EXPR(1)')->find('parse_gcol_expr')[0];

        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('PARSE_GCOL_EXPR is not a client statement.');

        (new ColumnRule($lowering))->parse($node);
    }
}
