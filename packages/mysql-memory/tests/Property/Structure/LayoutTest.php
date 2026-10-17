<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

/**
 * Directly constructed observable spellings preserve their expression and result name.
 */
#[CoversNothing]
#[Medium]
final class LayoutTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * Executes directly constructed expressions with independently chosen layout trivia.
     *
     * @throws \MySqlMemory\Error\SqlError When the constructed query cannot execute
     */
    #[DataProvider('providerSeeds')]
    public function testConstructedLayoutsKeepTheValueAndColumnName(string $seed): void
    {
        $choices = new Choices('layout-structure:' . $seed);
        $left = $choices->integer(0, 100);
        $right = $choices->integer(0, 100);
        $release = $choices->one([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910]);
        $mode = $choices->one(['', 'ANSI_QUOTES', 'NO_BACKSLASH_ESCAPES', 'IGNORE_SPACE', 'HIGH_NOT_PRECEDENCE,PIPES_AS_CONCAT']);
        $profile = new LanguageProfile($release, new LexicalSettings($mode));
        $layout = new Layout([new Spelled('', (string) $left), new Spelled($choices->one(['', '/* before */', "\t"]), '+'), new Spelled($choices->one(['', '/* after */', "\n"]), (string) $right)]);
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral((string) $left), new NumberLiteral((string) $right));
        $item = new SelectExpression($expression, layout: $layout, spellingProfile: $profile);
        $text = '\\' . $right;
        $plain = new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new StringLiteral([$text])));
        $quoted = $mode === 'NO_BACKSLASH_ESCAPES' ? $text : '\\' . $text;
        $reply = (new Execution())->run(new Select([], [$item, $plain]), substr($release->value, 6), mode: $mode);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) ($left + $right), '1']], $reply->rows);
        self::assertSame($layout->text(), $reply->columns[0]->name);
        self::assertSame("1 + '" . $quoted . "'", $reply->columns[1]->name);
    }
}
