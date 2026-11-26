<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Generation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\GeneratedLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\GeneratedLowering
 */
final class GeneratedLoweringTest extends TestCase
{
    public function testLowerRetainsTheLexicallyRegisteredFoldedMarker(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE t (a, b AS (?01 IN ()))');
        $definition = $operation->statement;
        self::assertInstanceOf(CreateTable::class, $definition);
        $constraint = $definition->columns[1]->generated();
        self::assertInstanceOf(Generated::class, $constraint);
        $binding = new SourceBinding($operation->binding(), $definition, [new Identifier('a'), new Identifier('b')]);

        $value = (new GeneratedLowering())->lower($constraint, $binding);

        self::assertSame(Storage::Virtual, $value->mode);
        self::assertInstanceOf(FoldedMembership::class, $value->expression);
        self::assertSame('01', $value->expression->bindings[0]->label);
    }
}
