<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Name\ColumnQualifiers;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ColumnQualifiers::class)]
#[Small]
final class ColumnQualifiersTest extends TestCase
{
    public function testCheckRejectsAnUnconditionallyDifferentTable(): void
    {
        $this->expectExceptionMessage('A definition name qualifier must identify the table of its statement.');
        new AlterTable(new QualifiedName(new Name('t')), [new DropElement(ElementKind::Column, new ColumnName(new Name('a'), new QualifiedName(new Name('other'))))]);
    }

    public function testCheckRejectsAnUnconditionallyDifferentExplicitDatabase(): void
    {
        $this->expectExceptionMessage('A definition name qualifier must identify the table of its statement.');
        new AlterTable(new QualifiedName(new Name('t'), new Name('d')), [new DropElement(ElementKind::Column, new ColumnName(new Name('a'), new QualifiedName(new Name('t'), new Name('other'))))]);
    }

    public function testProblemWithAnOmittedTargetDatabaseDependsOnTheContext(): void
    {
        $name = new ColumnName(new Name('a'), new QualifiedName(new Name('t'), new Name('d')));
        $target = new QualifiedName(new Name('t'));
        $rule = new ColumnQualifiers();
        $rule->check($target, [new DropElement(ElementKind::Column, $name)]);
        self::assertNull($rule->problem($name, $target));
        self::assertNull($rule->problem($name, $target, Comparison::Sensitive, new Name('d')));
        self::assertNotNull($rule->problem($name, $target, Comparison::Sensitive, new Name('other')));
    }

    public function testProblemWithCaseOnlyDifferencesDependOnNameComparison(): void
    {
        $name = new ColumnName(new Name('a'), new QualifiedName(new Name('T'), new Name('D')));
        $target = new QualifiedName(new Name('t'), new Name('d'));
        self::assertNull((new ColumnQualifiers())->problem($name, $target));
        self::assertNotNull((new ColumnQualifiers())->problem($name, $target, Comparison::Sensitive));
    }

    public function testAcceptsRequiresEveryQualifierToNameTheTable(): void
    {
        $rules = new ColumnQualifiers();
        $target = new QualifiedName(new Name('t'));

        self::assertTrue($rules->accepts($target, [new DropElement(ElementKind::Column, new ColumnName(new Name('a'), new QualifiedName(new Name('t'))))]));
        self::assertFalse($rules->accepts($target, [new DropElement(ElementKind::Column, new ColumnName(new Name('a'), new QualifiedName(new Name('other'))))]));
    }
}
