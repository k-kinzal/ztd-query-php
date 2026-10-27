<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DeletionInput::class)]
#[Small]
final class DeletionInputTest extends TestCase
{
    public function testTheInputKeepsTheTargetNamespaceTogether(): void
    {
        $target = new QualifiedName(new Name('t'));
        $table = new TableReference(new QualifiedName(new Name('t'), new Name('d')));
        $input = new DeletionInput([$table], [$target]);

        self::assertSame([$target], $input->targets);
        self::assertSame([$table], $input->tables);
        self::assertEquals([$table->name], (new DeletionInput([$table]))->targets);
    }

    public function testAnAbsentTargetIsRejected(): void
    {
        $this->expectExceptionMessage('Every DELETE target must name a possible input occurrence.');
        new DeletionInput([new TableReference(new QualifiedName(new Name('t')))], [new QualifiedName(new Name('x'))]);
    }

    public function testInitializeResolutionsKeepsTheBoundInputOccurrence(): void
    {
        $table = new TableReference(new QualifiedName(new Name('t')));
        $input = new DeletionInput([$table]);
        $input->initializeResolutions([$table]);
        self::assertSame($table, $input->relation($input->targets[0]));
        $this->expectExceptionMessage('A deletion input is resolved once.');
        $input->initializeResolutions([null]);
    }

    public function testRelationRetainsAnUnresolvedTarget(): void
    {
        $table = new TableReference(new QualifiedName(new Name('t')));
        $input = new DeletionInput([$table], resolutions: [null]);
        self::assertNull($input->relation($input->targets[0]));
    }

}
