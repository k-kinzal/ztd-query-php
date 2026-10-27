<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ElementFacts::class)]
#[Medium]
final class ElementFactsTest extends TestCase
{
    public function testElementResolvesTheReferences(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, FOREIGN KEY (a) REFERENCES p (x))');
        $statement = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($create);
        self::assertInstanceOf(CreateTable::class, $statement);
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $element1 = $statement->elements[1];
        self::assertInstanceOf(ForeignKey::class, $element1);
        (new ElementFacts())->element($statement->elements[1], $derivation, $derivation->environment());

        self::assertInstanceOf(MissingTable::class, $element1->references->relationInformation()->table);
    }

    public function testSpecificationDerivesTheExpressions(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT DEFAULT (1 + 2))');
        $statement = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($create);
        self::assertInstanceOf(CreateTable::class, $statement);
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $element0 = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element0);
        $specification = $element0->specification;
        (new ElementFacts())->specification($specification, $derivation, $derivation->environment());

        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOptionsAcceptsTableOptions(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT) ENGINE x');
        $statement = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($create);
        self::assertInstanceOf(CreateTable::class, $statement);
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        (new ElementFacts())->options($statement->options, $derivation);

        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testDeclarationAnswersTheColumnDeclaration(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT AUTO_INCREMENT)');
        $statement = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($create);
        self::assertInstanceOf(CreateTable::class, $statement);

        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);

        self::assertSame(Nullability::NotNull, (new ElementFacts())->declaration($column)->nullability);
    }

    public function testOptionsPreservesTheOrderOfAbortingNameFailures(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(a INT) CHARACTER SET missing COLLATE other AS SELECT BINARY 1');
        $warnings = $create->warnings();

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warnings[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset::class, $warnings[0]->problem);
        self::assertTrue($warnings[0]->aborts);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warnings[1]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $warnings[1]->problem);
        self::assertTrue($warnings[1]->aborts);
    }

    public function testEncodingRejectsACollationBeforeItsFirstReleaseWithoutSql(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.7.44', null, ParameterStyle::Native), null, [], true));
        $name = new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4_0900_ai_ci'));
        (new ElementFacts())->encoding($name, $derivation, $name);
        $warnings = $derivation->facts()->warnings;

        self::assertCount(1, $warnings);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warnings[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $warnings[0]->problem);
        self::assertTrue($warnings[0]->aborts);
    }

    public function testEncodingLeavesDefaultToTheDatabaseContext(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $name = new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(null);
        (new ElementFacts())->encoding($name, $derivation, $name);

        self::assertSame([], $derivation->facts()->diagnostics);
        self::assertSame([], $derivation->facts()->warnings);
    }

    public function testReferencesAnswersTheInlineReference(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT REFERENCES p (x))');
        $statement = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($create);
        self::assertInstanceOf(CreateTable::class, $statement);

        $element0 = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element0);
        self::assertSame('p', (new ElementFacts())->references($element0->specification)?->table->name->value);
    }
    public function testNamesResolvesTheDefinitionQualifierBeforeExpressionFacts(): void
    {
        $name = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('other')));
        $element = new ColumnDefinition($name, new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        (new ElementFacts())->names($element, $derivation, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName::class, $derivation->facts()->diagnostics[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $derivation->facts()->warnings[0]);
    }

}
