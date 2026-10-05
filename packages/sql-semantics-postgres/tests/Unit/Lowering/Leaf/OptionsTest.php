<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Options;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionAction;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Options::class)]
#[Small]
final class OptionsTest extends TestCase
{
    public function testDefinitionsLowersADefinitionList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TYPE t (a = 1, b)');
        $definitions = $lowering->options->definitions($tree->find('definition')[0]);
        self::assertSame(['a', 'b'], [$definitions[0]->name->value, $definitions[1]->name->value]);
        self::assertNull($definitions[1]->argument);
    }

    public function testDefinitionsLowersStorageParametersAndTheirAbsence(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (a) WITH (fillfactor = 70, toast.x = on)');
        $options = $lowering->options->definitions($tree->find('opt_reloptions')[0]);
        self::assertSame('toast', $options[1]->qualifier?->value);
        self::assertSame([], $lowering->options->definitions((new PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (a)')->find('opt_reloptions')[0]));
    }

    public function testDefinitionLowersOneElement(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TYPE t (a = 1)');
        self::assertInstanceOf(SignedNumber::class, $lowering->options->definition($tree->find('def_elem')[0])->argument);
    }

    public function testElementsLowersNamesAndWrittenValues(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TYPE t (a = 1, b)');
        $elements = $lowering->options->elements($tree->find('definition')[0]);
        self::assertSame(['a', 'b', null], [$elements[0][0]->value, $elements[1][0]->value, $elements[1][1]]);
    }

    public function testElementLowersANameWithAValue(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE TYPE t (a = 'x')");
        [$name, $value] = $lowering->options->element($tree->find('def_elem')[0]);
        self::assertSame(['a', 'x'], [$name->value, $value instanceof StringConstant ? $value->value : null]);
    }

    public function testArgumentLowersEveryValueKind(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE TYPE t (a = int4, b = select, c = OPERATOR(pg_catalog.+), d = -1.5, e = 'x', f = none, g = t.a%TYPE)");
        $arguments = array_map(static fn (\SqlParser\Parser\Node $node): OptionArgument => $lowering->options->argument($node), $tree->find('def_arg'));
        self::assertSame([TypeName::class, KeywordWord::class, OperatorName::class, SignedNumber::class, StringConstant::class, KeywordWord::class, TypeName::class], array_map(static fn (OptionArgument $argument): string => $argument::class, $arguments));
    }

    public function testGenericOptionsLowersTheOptionsOfAForeignDataObject(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE SERVER s FOREIGN DATA WRAPPER w OPTIONS (host 'h', \"Port\" '5432')");
        $options = $lowering->options->genericOptions($tree->find('create_generic_options')[0]);
        self::assertSame(['Port', '5432'], [$options[1]->name->value, $options[1]->value->value]);
        self::assertSame([], $lowering->options->genericOptions((new PostgreSqlParser('pg-17.2'))->parse('CREATE SERVER s FOREIGN DATA WRAPPER w')->find('create_generic_options')[0]));
    }

    public function testGenericOptionLowersNameAndValue(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("CREATE SERVER s FOREIGN DATA WRAPPER w OPTIONS (host 'h')");
        [$name, $value] = $lowering->options->genericOption($tree->find('generic_option_elem')[0]);
        self::assertSame(['host', 'h'], [$name->value, $value->value]);
    }

    public function testAlteredOptionsAssumesAddWithoutAnAction(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("ALTER SERVER s OPTIONS (ADD a '1', SET b '2', DROP c, d '4')");
        $options = $lowering->options->alteredOptions($tree->find('alter_generic_options')[0]);
        self::assertSame([OptionAction::Add, OptionAction::Set, OptionAction::Drop, OptionAction::Add], array_map(static fn (AlteredOption $option): OptionAction => $option->action, $options));
    }

    public function testWordOrStringKeepsTheWrittenKind(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SET x TO Foo, 'Foo'");
        $values = $tree->find('NonReservedWord_or_Sconst');
        $word = $lowering->options->wordOrString($values[0]);
        self::assertInstanceOf(Word::class, $word);
        self::assertSame('foo', $word->word->value);
        self::assertInstanceOf(StringConstant::class, $lowering->options->wordOrString($values[1]));
    }

    public function testValueLowersKeywordsWordsStringsAndNumbers(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SET x TO on, true, 's', w, -5");
        $values = array_map(static fn (\SqlParser\Parser\Node $node): OptionArgument => $lowering->options->value($node), $tree->find('var_value'));
        self::assertSame(Toggle::On, $values[0]);
        self::assertSame(Toggle::True, $values[1]);
        self::assertInstanceOf(StringConstant::class, $values[2]);
        self::assertInstanceOf(Word::class, $values[3]);
        self::assertInstanceOf(SignedNumber::class, $values[4]);
    }

    public function testValuesLowersAValueList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SET x TO a, b');
        self::assertCount(2, $lowering->options->values($tree->find('var_list')[0]));
    }

    public function testVariableLowersTheDottedParameterName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SET a.b TO 1');
        self::assertSame(['a', 'b'], array_map(static fn (Name $name): string => $name->value, $lowering->options->variable($tree->find('var_name')[0])));
    }
}
