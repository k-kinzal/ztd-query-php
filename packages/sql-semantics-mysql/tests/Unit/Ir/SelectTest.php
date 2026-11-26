<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SearchedCaseArm;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\From\DerivedSource;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(Select::class)]
#[Small]
final class SelectTest extends TestCase
{
    public function testToStringRendersAnIndependentSnapshot(): void
    {
        $column = new ResultColumn(new IntegerLiteral('2'), new Identifier('a'));
        $select = new Select([&$column]);
        $column = new ResultColumn(new IntegerLiteral('3'), new Identifier('changed'));

        self::assertSame('SELECT 2 AS `a`', $select->toString());
        self::assertSame('changed', $column->name->value);
    }

    public function testProfileIdIdentifiesTheFixedRelease(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('2'), new Identifier('a'))]);

        self::assertStringStartsWith('mysql-9.1.0/', $select->profileId());
    }

    public function testRefusesADerivedJoinInputInAnotherLexicalMode(): void
    {
        $column = new ResultColumn(new IntegerLiteral('3'), new Identifier('n'));
        $ansi = new DerivedSource(new Select([$column], mode: ProjectionMode::AnsiQuotes));
        $ordinary = new DerivedSource(new Select([$column]));
        $input = new Join(JoinKind::Cross, $ansi, $ordinary);

        $this->expectExceptionMessage('A derived input must use its owning SELECT lexical mode.');

        new Select([$column], $input, mode: ProjectionMode::AnsiQuotes);
    }

    public function testRefusesUnboundColumnSlots(): void
    {
        $this->expectExceptionMessage('belong to the SELECT source');

        new Select([new ResultColumn(new ColumnReference(0), new Identifier('a'))]);
    }

    public function testToStringUsesTheIntrinsicFieldLabel(): void
    {
        $field = new Identifier(' amount');
        $source = new TableSource(new Identifier('app'), new Identifier('events'), [$field]);
        $value = new Select([new ResultColumn(new ColumnReference(0), $field)], $source);

        $quote = chr(96);
        self::assertSame('SELECT ' . $quote . '__ztd_source' . $quote . '.' . $quote . ' amount' . $quote
            . ' FROM ' . $quote . 'app' . $quote . '.' . $quote . 'events' . $quote . ' AS ' . $quote . '__ztd_source' . $quote, $value->toString());
    }

    public function testSignedCaseLayoutDistinguishesNullStorageAndMissingPosition(): void
    {
        $null = new NullLiteral();
        $case = new SignedCase([new SearchedCaseArm($null, $null)]);
        $result = new Select([new ResultColumn($case, new Identifier('answer'))]);

        self::assertSame(['storage' => null, 'nullable' => true], $result->signedCaseLayout(0));
        self::assertNull($result->signedCaseLayout(1));
    }
}
