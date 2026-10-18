<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Resolution;

use MySqlMemory\Instance;
use MySqlMemory\Session\Resolution\TablePreparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(TablePreparation::class)]
#[Small]
final class TablePreparationTest extends TestCase
{
    public function testDeclarationsRetainsTheBoundInstructionAndItsFacts(): void
    {
        $definition = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')));
        $derivation = new Derivation((new Instance())->connect()->semantics()->context());
        $definition->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $facts = $derivation->facts();
        $table = $facts->relation($definition)->table;
        self::assertInstanceOf(DeclaredTable::class, $table);
        $prepared = new TablePreparation($definition, $facts, $table->table);
        self::assertSame($definition, $prepared->statement);
        self::assertSame($facts, $prepared->facts);
        self::assertSame([$table->table], $prepared->declarations());
    }
}
