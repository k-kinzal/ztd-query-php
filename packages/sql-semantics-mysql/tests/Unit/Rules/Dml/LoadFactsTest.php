<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\LoadFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(LoadFacts::class)]
#[Medium]
final class LoadFactsTest extends TestCase
{
    /**
     * @return iterable<string, array{LoadTable, ?LoadRule}>
     */
    public static function providerAlgorithms(): iterable
    {
        $target = new WriteTarget(new QualifiedName(new Name('t')));
        $file = new LoadInput(false, LoadSource::Infile, new Text('f'));
        $local = new LoadInput(true, LoadSource::Url, new Text('f'), new Numeral('2'));
        $bulk = new BulkOptions(bulk: true);
        yield 'XML before LOCAL and IGNORE' => [new LoadTable(LoadFormat::Xml, null, $local, DuplicateHandling::Ignore, $target, bulk: $bulk), LoadRule::XmlBulk];
        yield 'LOCAL before IGNORE' => [new LoadTable(LoadFormat::Data, null, $local, DuplicateHandling::Ignore, $target, bulk: $bulk), LoadRule::LocalBulk];
        yield 'IGNORE' => [new LoadTable(LoadFormat::Data, null, $file, DuplicateHandling::Ignore, $target, bulk: $bulk), LoadRule::IgnoreBulk];
        yield 'files before URL and compression' => [new LoadTable(LoadFormat::Data, null, $local, null, $target, compression: new Text('x')), LoadRule::MultipleFilesWithoutBulk];
        yield 'URL before compression' => [new LoadTable(LoadFormat::Data, null, new LoadInput(false, LoadSource::Url, new Text('f')), null, $target, compression: new Text('x')), LoadRule::UrlWithoutBulk];
        yield 'compression' => [new LoadTable(LoadFormat::Data, null, $file, null, $target, compression: new Text('x')), LoadRule::CompressionWithoutBulk];
        yield 'bulk capability belongs to execution' => [new LoadTable(LoadFormat::Data, null, $file, DuplicateHandling::Replace, $target, bulk: $bulk), null];
        yield 'ordinary file' => [new LoadTable(LoadFormat::Data, null, $file, null, $target), null];
    }

    #[DataProvider('providerAlgorithms')]
    public function testAlgorithmResolvesConstructedOptions(LoadTable $load, ?LoadRule $expected): void
    {
        self::assertSame($expected, (new LoadFacts())->algorithm($load)?->rule);
    }

    public function testDerivePublishesTheAlgorithmConflictBeforeTargetResolution(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("LOAD XML INFILE 'f' INTO TABLE t ALGORITHM = BULK");

        self::assertInstanceOf(LoadConflict::class, $operation->facts->diagnostics[0]);
        self::assertSame(LoadRule::XmlBulk, $operation->facts->diagnostics[0]->rule);
    }

    public function testDeriveReportsAnUnknownColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze("LOAD DATA INFILE 'f' INTO TABLE t (a, z)", [$t]);

        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveFindsTheInvisibleColumnsOfTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE v (a INT, e INT INVISIBLE)')->declarations();

        self::assertSame([], $semantics->analyze("LOAD DATA INFILE 'f' INTO TABLE v (a, e)", $tables)->facts->diagnostics);
    }

    public function testDeriveWarnsOfTheCharacterSetTheStatementNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (a INT)');
        $facts = $semantics->analyze("LOAD DATA INFILE 'f' INTO TABLE t CHARACTER SET utf8", [$t])->facts;

        self::assertSame([\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Utf8Alias->value], array_map(static fn ($warning): string => $warning->message(), $facts->warnings));
    }
}
