<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind;
use SqlSemantics\Model\Definition\MySqlTable\Key\SetConstraintEnforcement;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetConstraintEnforcement::class)]
#[Medium]
final class SetConstraintEnforcementTest extends TestCase
{
    public function testReadsAConstraintAddress(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALTER CONSTRAINT c ENFORCED');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(SetConstraintEnforcement::class, $alteration);
        self::assertSame(['c', KeyKind::Constraint, true], [$alteration->name, $alteration->kind, $alteration->enforced]);
    }

    public function testRejectsAForeignKeyAddress(): void
    {
        $this->expectException(InvalidStructure::class);
        new SetConstraintEnforcement('c', KeyKind::ForeignKey, true);
    }
}
