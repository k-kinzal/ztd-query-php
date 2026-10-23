<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Program\VariableDomain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(VariableDomain::class)]
#[Small]
final class VariableDomainTest extends TestCase
{
    public function testDeclaredTypesAStringInTheCollationOfTheDatabaseWithoutDecimals(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE a VARCHAR(5); DECLARE b TINYINT UNSIGNED; END');
        $statement = $create->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $statement);
        $block = $statement->body;
        self::assertInstanceOf(Block::class, $block);
        [$a, $b] = $block->declarations;
        self::assertInstanceOf(VariableDeclaration::class, $a);
        self::assertInstanceOf(VariableDeclaration::class, $b);

        $string = VariableDomain::declared($a->type, $a->collation, Collation::known('latin1_swedish_ci'));
        $integer = VariableDomain::declared($b->type, $b->collation, Collation::known('latin1_swedish_ci'));

        self::assertSame(['latin1_swedish_ci', 5, 0, true, true], [$string->collation->name, $string->length, $string->decimals, $string->nullable, $integer->unsigned]);
    }
}
