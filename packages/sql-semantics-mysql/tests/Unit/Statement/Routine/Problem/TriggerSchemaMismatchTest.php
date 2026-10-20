<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\TriggerSchemaMismatch;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TriggerSchemaMismatch::class)]
#[Small]
final class TriggerSchemaMismatchTest extends TestCase
{
    public function testMessageDescribesTheExplicitRelationship(): void
    {
        $name = new QualifiedName(new Name('tr'), new Name('a'));
        $table = new TriggerTable(new QualifiedName(new Name('t'), new Name('b')));
        $problem = new TriggerSchemaMismatch($name, $table);
        self::assertSame('Trigger in wrong schema', $problem->message());
        self::assertSame($name, $problem->name);
        self::assertSame($table, $problem->table);
    }

    public function testAStateDependentRelationshipCannotBeAStaticRefusal(): void
    {
        $this->expectExceptionMessage('A trigger-schema refusal requires distinct explicit database qualifiers.');
        new TriggerSchemaMismatch(new QualifiedName(new Name('tr')), new TriggerTable(new QualifiedName(new Name('t'), new Name('b'))));
    }
}
