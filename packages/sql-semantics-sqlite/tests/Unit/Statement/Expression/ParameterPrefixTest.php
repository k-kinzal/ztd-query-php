<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(ParameterPrefix::class)]
#[Medium]
final class ParameterPrefixTest extends TestCase
{
    public function testFromReadsEachPrefixCharacter(): void
    {
        self::assertSame(ParameterPrefix::Question, ParameterPrefix::from('?'));
        self::assertSame(ParameterPrefix::Colon, ParameterPrefix::from(':'));
        self::assertSame(ParameterPrefix::At, ParameterPrefix::from('@'));
        self::assertSame(ParameterPrefix::Dollar, ParameterPrefix::from('$'));
    }

    public function testCasesListTheFourPrefixes(): void
    {
        self::assertSame(['?', ':', '@', '$'], array_map(static fn (ParameterPrefix $prefix): string => $prefix->value, ParameterPrefix::cases()));
    }

    public function testFromMatchesThePrefixOfAnAnalyzedParameter(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT $id')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(BindParameter::class, $statement->columns[0]->expression);
        self::assertSame(ParameterPrefix::Dollar, $statement->columns[0]->expression->prefix);
    }
}
