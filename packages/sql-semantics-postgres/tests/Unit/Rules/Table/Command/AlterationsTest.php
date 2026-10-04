<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations::class)]
#[Medium]
final class AlterationsTest extends TestCase
{
    public function testDeriveDerivesEachAction(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('ALTER TABLE t ALTER zz DROP DEFAULT, ADD CHECK (yy)', $context);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testWidenedAddsTheNewColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('ALTER TABLE t ADD n int NOT NULL CHECK (n > a)', $context);
        self::assertSame(0, count($statement->facts->diagnostics));
    }

    public function testColumnReportsAColumnTheTableLacks(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('ALTER TABLE t ALTER zz SET STORAGE plain', $context);
        self::assertSame([
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testAdmitsRefusesAPartitionActionAmongOthers(): void
    {
        self::assertSame(false, (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations())->admits([new \SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachIndex(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('i'))), new \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableAction(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind::SetLogged)]));
    }
}
