<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\IndexOptions;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(IndexOptions::class)]
#[Small]
final class IndexOptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{IndexKind, IndexOption, string}>
     */
    public static function providerIncompatibleOptions(): iterable
    {
        yield 'fulltext using' => [IndexKind::FullText, new IndexUsing(IndexAlgorithm::Btree), 'A FULLTEXT or SPATIAL index has no USING option.'];
        yield 'spatial using' => [IndexKind::Spatial, new IndexUsing(IndexAlgorithm::Hash), 'A FULLTEXT or SPATIAL index has no USING option.'];
        yield 'ordinary parser' => [IndexKind::Index, new IndexParser(new Name('p')), 'WITH PARSER is an option of a FULLTEXT index.'];
        yield 'spatial parser' => [IndexKind::Spatial, new IndexParser(new Name('p')), 'WITH PARSER is an option of a FULLTEXT index.'];
    }

    #[DataProvider('providerIncompatibleOptions')]
    public function testCheckRejectsAnOptionOfAnotherIndexKind(IndexKind $kind, IndexOption $option, string $message): void
    {
        $this->expectExceptionMessage($message);
        (new IndexOptions())->check($kind, [$option]);
    }

    public function testCheckAcceptsTheOptionOfItsOwnIndexKind(): void
    {
        $options = new IndexOptions();
        $using = new IndexUsing(IndexAlgorithm::Btree);
        $parser = new IndexParser(new Name('p'));
        $options->check(IndexKind::Primary, [$using]);
        $options->check(IndexKind::FullText, [$parser]);

        self::assertSame(IndexAlgorithm::Btree, $using->algorithm);
        self::assertSame('p', $parser->parser->value);
    }

    public function testParsersRetainsAnAbortingDependencyBeforeLaterOptions(): void
    {
        $settings = new Settings(Collation::known('utf8mb4_bin'), fullTextParsers: [new Name('ngram')]);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([], true, null, $settings));
        $options = [new IndexParser(new Name('ngram')), new IndexParser(new Name('missing_parser'))];
        (new IndexOptions())->parsers($options, $derivation);
        $facts = $derivation->facts();

        self::assertCount(1, $facts->diagnostics);
        self::assertCount(1, $facts->warnings);
        self::assertInstanceOf(ParseFailure::class, $facts->warnings[0]);
        self::assertTrue($facts->warnings[0]->aborts);
        self::assertSame($facts->diagnostics[0], $facts->warnings[0]->problem);
        self::assertSame("Function 'missing_parser' is not defined", $facts->warnings[0]->message());
    }
}
