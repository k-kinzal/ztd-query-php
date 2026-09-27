<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Forms;
use SqlSemantics\Core\Language;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Resolving;

#[CoversClass(Forms::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Resolver::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\NameSites::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Policy\RelationRules::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(Traversal::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[Medium]
final class FormsTest extends TestCase
{
    public function testPositionFindsTheFirstFreePositionOfASymbol(): void
    {
        $forms = new Forms((new Language(MySqlDialect::MySql))->vocabulary());
        self::assertSame(4, $forms->position(['DROP', 'opt_temporary', 'table_or_tables', 'if_exists', 'table_list', 'opt_restrict'], 'table_list'));
        self::assertSame(0, $forms->position(['ident', '.', 'ident'], 'ident'));
        self::assertSame(2, $forms->position(['ident', '.', 'ident'], 'ident', [0 => true]));
    }

    public function testChildCountsOnlyTheRuleSymbols(): void
    {
        $language = new Language(MySqlDialect::MySql);
        $forms = new Forms($language->vocabulary());
        $drop = ['DROP', 'opt_temporary', 'table_or_tables', 'if_exists', 'table_list', 'opt_restrict'];
        $statement = (new Semantics(MySqlDialect::MySql))->analyze('DROP TABLE a, b');
        $statement = Resolving::form(MySqlDialect::MySql, $statement->command, 'drop_table_stmt');
        self::assertSame('a , b', Writer::render($forms->child($drop, $statement->children(), 4)));
        self::assertSame('TABLE', Writer::render($forms->child($drop, $statement->children(), 2)));
    }

    public function testConditionalReadsFixedWordsAndOptionalValues(): void
    {
        $language = new Language(MySqlDialect::MySql);
        $forms = new Forms($language->vocabulary());
        $drop = ['DROP', 'opt_temporary', 'table_or_tables', 'if_exists', 'table_list', 'opt_restrict'];
        $conditional = Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('DROP TABLE IF EXISTS a')->command, 'drop_table_stmt');
        $plain = Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('DROP TABLE a')->command, 'drop_table_stmt');
        self::assertTrue($forms->conditional($drop, $conditional->children(), 'if_exists'));
        self::assertFalse($forms->conditional($drop, $plain->children(), 'if_exists'));
        self::assertTrue($forms->conditional($drop, $plain->children(), 'DROP'));
        self::assertFalse($forms->conditional($drop, $plain->children(), 'VIEW_SYM'));
        self::assertFalse($forms->conditional($drop, $plain->children(), 'table_ident'));
    }

    public function testUnfoldFlattensALeftRecursiveListInWritingOrder(): void
    {
        $language = new Language(MySqlDialect::MySql);
        $forms = new Forms($language->vocabulary());
        $drop = ['DROP', 'opt_temporary', 'table_or_tables', 'if_exists', 'table_list', 'opt_restrict'];
        $list = Resolving::form(MySqlDialect::MySql, (new Semantics(MySqlDialect::MySql))->analyze('DROP TABLE a, b, c')->command, 'table_list');
        $items = $forms->unfold($list, 'table_list', ['table_list', ',', 'table_ident']);
        self::assertSame(['a', 'b', 'c'], array_map(static fn (Element $item): string => Writer::render($item), $items));
        self::assertSame([$list], $forms->unfold($list, 'table_list', ['table_ident']));
    }
}
