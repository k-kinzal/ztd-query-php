<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;

#[CoversClass(AddConstraint::class)]
#[Medium]
final class AddConstraintTest extends TestCase
{
    public function testDeriveCommandDerivesTheElementInTheChangedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t ADD CHECK (a > x)', [$table]);

        self::assertSame('Column x does not exist.', $alter->diagnostics()[0]->message());
    }

    public function testRenderWritesTheElement(): void
    {
        self::assertSame('ALTER TABLE t ADD UNIQUE KEY u (a)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD UNIQUE KEY u (a)')->toString());
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function providerConstraintElements(): iterable
    {
        yield 'index' => ['INDEX i (a)', \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition::class];
        yield 'foreign key' => ['FOREIGN KEY (a) REFERENCES parent (id)', \SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey::class];
        yield 'check constraint' => ['CHECK (a > 0)', \SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('providerConstraintElements')]
    public function testRenderPreservesEachKeyAndConstraintKind(string $element, string $class): void
    {
        $sql = 'ALTER TABLE t ADD ' . $element;
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $operation);
        $command = $operation->commands[0];
        self::assertInstanceOf(AddConstraint::class, $command);
        self::assertInstanceOf($class, $command->element);
        self::assertSame($sql, $operation->toString());
    }
}
