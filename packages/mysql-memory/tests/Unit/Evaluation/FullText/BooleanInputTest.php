<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\FullText;

use MySqlMemory\Evaluation\FullText\BooleanInput;
use MySqlMemory\Evaluation\FullText\Words;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(BooleanInput::class)]
#[Small]
final class BooleanInputTest extends TestCase
{
    public function testReadKeepsGroupsPhrasesPrefixesAndModifiers(): void
    {
        $words = new Words(Collation::known('utf8mb4_bin'));
        $terms = BooleanInput::read('+apple -(banana pear) "orange cherry" @3 ap*', Charset::known('utf8mb4'), $words, false);
        self::assertSame('+', $terms[0]->operator);
        self::assertSame('-', $terms[1]->operator);
        self::assertSame(['pear'], $terms[1]->children[1]->words);
        self::assertSame(3, $terms[2]->distance);
        self::assertTrue($terms[3]->prefix);
    }

    public function testGroupConsumesTheClosingDelimiter(): void
    {
        $position = 0;
        $terms = (new BooleanInput(['apple', ')', 'pear'], new Words(Collation::known('utf8mb4_bin')), false))->group($position, true);
        self::assertCount(1, $terms);
        self::assertSame(2, $position);
    }

    public function testOperatorPreservesRepeatedMyIsamWeights(): void
    {
        $position = 0;
        self::assertSame('+>>', (new BooleanInput(['+', '>', '>', 'apple'], new Words(Collation::known('utf8mb4_bin')), true))->operator($position));
        self::assertSame(3, $position);
    }

    public function testTermReadsProximityAfterAQuotedValue(): void
    {
        $position = 0;
        $term = (new BooleanInput(['@', '4'], new Words(Collation::known('utf8mb4_bin')), false))->term('"apple pear"', '', $position);
        self::assertSame(['apple', 'pear'], $term->words);
        self::assertSame(4, $term->distance);
    }

    public function testErrorReportsTheSearchValueSyntax(): void
    {
        $error = (new BooleanInput([], new Words(Collation::known('utf8mb4_bin')), false))->error("'>'");
        self::assertSame(1064, $error->getCode());
        self::assertSame('42000', $error->sqlState());
        self::assertSame("syntax error, unexpected '>'", $error->getMessage());
    }

    public function testReadRejectsRepeatedInnoDbModifiers(): void
    {
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionMessage("syntax error, unexpected '+'");
        BooleanInput::read('++apple', Charset::known('utf8mb4'), new Words(Collation::known('utf8mb4_bin')), false);
    }
}
