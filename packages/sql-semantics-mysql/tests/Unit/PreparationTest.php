<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Preparation;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Validation\ValueGraph;

#[CoversClass(Preparation::class)]
#[Large]
final class PreparationTest extends TestCase
{
    #[DataProvider('providerInputs')]
    public function testSharedInterpretationsEqualIndependentDecoding(string $sql): void
    {
        $initial = new Interpretation(new LanguageProfile(GrammarRelease::MySql847), Charset::known('utf8mb4'));
        $reader = new Preparation();
        $definition = $reader->read($sql, $initial);
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
        $equivalence = new \SqlSemantics\Validation\Equivalence();
        $differences = array_map(static function ($alternative) use ($reader, $sql, $platform, $parser, $equivalence): ?string {
            $independent = $reader->alternative($sql, $alternative->interpretation, $platform, $parser);
            return $equivalence->difference($alternative, $independent, true);
        }, $definition->alternatives);
        self::assertSame([], array_filter($differences, static fn (?string $difference): bool => $difference !== null));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInputs(): iterable
    {
        yield 'modes and parameters' => ['SELECT ?, "a", 1||2, NOT 1 BETWEEN 0 AND 2, COUNT (1) FROM t'];
        yield 'escaped quote' => ["SELECT 'left\\' AS changed FROM u -- ' FROM t"];
        yield 'non-ASCII identifier' => ["SELECT `\xC3\xA9` FROM t"];
        yield 'radix literals' => ["SELECT X'80', B'10000000', 0x80"];
    }

    public function testReadCapturesClientAndModeClosureWithoutRetainingAnalysisState(): void
    {
        $initial = new Interpretation(new LanguageProfile(GrammarRelease::MySql847), Charset::known('utf8mb4'));
        $definition = (new Preparation())->read('SELECT ?, "a", 1||2 FROM t', $initial, new Name('d'));
        self::assertCount(1184, $definition->alternatives);
        self::assertSame('d', $definition->database?->value);
        $clients = array_count_values(array_map(static fn ($alternative): string => $alternative->interpretation->client->name, $definition->alternatives));
        self::assertSame([1], array_values(array_unique(array_map(static fn ($alternative): int => count($alternative->parameters), $definition->alternatives))));
        self::assertCount(37, $clients);
        self::assertSame([32], array_values(array_unique($clients)));
        self::assertSame([], array_intersect(['ucs2', 'utf16', 'utf16le', 'utf32'], array_keys($clients)));
        $same = new Interpretation(new LanguageProfile(GrammarRelease::MySql847), Charset::known('ascii'));
        self::assertSame($definition->select($initial)->statement, $definition->select($same)->statement);
        $objects = (new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\MySql\\Statement\\']))->objects($definition);
        self::assertSame([], array_filter($objects, static fn (object $object): bool => $object instanceof \SqlSemantics\Contract\AnalysisContext || $object instanceof \SqlSemantics\Construction\Facts || $object instanceof \SqlParser\Parser\Node));
    }

    public function testAlternativeRespectsTheNamedParameterProfile(): void
    {
        $profile = new Interpretation(new LanguageProfile(GrammarRelease::MySql847, parameters: \SqlSemantics\Contract\ParameterStyle::Named), Charset::known('latin1'));
        $alternative = (new Preparation())->alternative('SELECT :value', $profile, new \SqlSemantics\Platform\MySql\Platform(), new \SqlParser\MySql\MySqlParser('mysql-8.4.7'));
        self::assertSame($profile, $alternative->interpretation);
        self::assertCount(1, $alternative->parameters);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\Parameter::class, $alternative->parameters[0]);
        self::assertSame(':value', $alternative->parameters[0]->marker);
    }

    public function testReadKeepsSyntaxFailuresSeparateFromTheAcceptedInterpretation(): void
    {
        $initial = new Interpretation(new LanguageProfile(GrammarRelease::MySql847), Charset::known('utf8mb4'));
        $definition = (new Preparation())->read("SELECT 'a\\'b' FROM t", $initial);
        self::assertInstanceOf(QueryRoot::class, $definition->select($initial)->statement);
        $changed = new Interpretation(new LanguageProfile(GrammarRelease::MySql847, new LexicalSettings('NO_BACKSLASH_ESCAPES')), $initial->client);
        self::assertInstanceOf(SyntaxRefusal::class, $definition->select($changed)->statement);
        self::assertSame(1, $definition->select($changed)->statement->line);
    }

    public function testReadPreservesClausesExposedByChangedQuoteBoundaries(): void
    {
        $initial = new Interpretation(new LanguageProfile(GrammarRelease::MySql847), Charset::known('utf8mb4'));
        $definition = (new Preparation())->read("SELECT 'left\\' AS changed FROM u -- ' FROM t", $initial);
        $changed = new Interpretation(new LanguageProfile(GrammarRelease::MySql847, new LexicalSettings('NO_BACKSLASH_ESCAPES')), $initial->client);
        $first = $definition->select($initial)->statement;
        $second = $definition->select($changed)->statement;
        self::assertInstanceOf(QueryRoot::class, $first);
        self::assertInstanceOf(QueryRoot::class, $second);
        self::assertInstanceOf(Select::class, $first->query);
        self::assertInstanceOf(Select::class, $second->query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Relation\TableReference::class, $first->query->from);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Relation\TableReference::class, $second->query->from);
        self::assertSame('t', $first->query->from->name->name->value);
        self::assertSame('u', $second->query->from->name->name->value);
    }
}
