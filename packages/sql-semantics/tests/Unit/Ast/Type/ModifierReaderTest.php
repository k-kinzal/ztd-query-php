<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\Type\ModifierForm;
use SqlSemantics\Ast\Type\ModifierReader;
use SqlSemantics\Dialect;

#[CoversClass(ModifierReader::class)]
#[Medium]
final class ModifierReaderTest extends TestCase
{
    #[TestWith(['NOT ROW(NULL)'])]
    #[TestWith(['1 + 2'])]
    #[TestWith(['+1'])]
    #[TestWith(['TRUE'])]
    #[TestWith(['NULL'])]
    #[TestWith(['t.width'])]
    public function testValidateRejectsGeneralExpressionsAsTypeModifiers(string $expression): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('DROP FUNCTION f(NUMERIC(' . $expression . '))');
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('simple numeric or text constants');
        ModifierReader::validate($source);
    }

    #[TestWith(['((12))', ModifierForm::Number])]
    #[TestWith(['-(-12)', ModifierForm::Number])]
    #[TestWith(["'12'", ModifierForm::Text])]
    #[TestWith(['"12"', ModifierForm::Identifier])]
    public function testFormClassifiesSimpleInputsWithoutEvaluatingValues(string $expression, ModifierForm $expected): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('DROP FUNCTION f(NUMERIC(' . $expression . '))');
        $argument = Tree::outer($source, ['a_expr'])[0];
        self::assertSame($expected, ModifierReader::form($argument));
    }
}
