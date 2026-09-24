<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Key\AddConstraint;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddConstraint::class)]
#[Medium]
final class AddConstraintTest extends TestCase
{
    public function testReadsTheConstraint(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD CONSTRAINT fk FOREIGN KEY (n) REFERENCES u (id)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddConstraint::class, $alteration);
        self::assertInstanceOf(ForeignKey::class, $alteration->constraint);
        self::assertSame('fk', $alteration->constraint->name);
    }
}
