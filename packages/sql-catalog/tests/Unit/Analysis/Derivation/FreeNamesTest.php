<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Extension\WordPressExtension;
use SqlCatalog\Php\SourceParser;

#[CoversClass(FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\ExternalInput::class)]
#[UsesClass(PdoExtension::class)]
#[UsesClass(\SqlCatalog\Extension\SinkSpec::class)]
#[UsesClass(WordPressExtension::class)]
#[UsesClass(\SqlCatalog\Php\ParsedFile::class)]
#[UsesClass(SourceParser::class)]
final class FreeNamesTest extends TestCase
{
    /**
     * @return list<array{string, list<string>}>
     */
    public static function providerRead(): array
    {
        return [
            ['"SELECT $c FROM " . $t', ['c', 't']],
            ['$_GET["id"]', []],
            ['$this->table . $suffix', ['this->table', 'suffix']],
            ['$this->find($id)', ['this', 'id']],
            ['function () use ($db, $t) { return $x; }', ['db', 't']],
            ['fn ($id) => $t . $id', ['t']],
            ['$rows[$key] = $value', ['value', 'rows', 'key']],
            ['$sql = $base', ['base']],
            ['[$a, $b] = $pair', ['pair']],
            ['$$dynamic', []],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerRead')]
    public function testReadNamesWhatTheValueDependsOn(string $expression, array $expected): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php ' . $expression . ';')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);

        self::assertSame($expected, array_keys((new FreeNames())->read($statement->expr)));
    }

    public function testOfJoinsTheNamesOfEveryExpression(): void
    {
        self::assertSame(['a', 'b'], array_keys((new FreeNames())->of([new Expr\Variable('a'), new Expr\Variable('b')])));
    }

    public function testVariableLeavesOutExternalInput(): void
    {
        $names = new FreeNames();

        self::assertSame(['sql' => true], $names->variable(new Expr\Variable('sql')));
        self::assertSame([], $names->variable(new Expr\Variable('_POST')));
    }

    public function testPropertyNameNamesOnlyAPropertyOfThis(): void
    {
        $names = new FreeNames();

        self::assertSame('this->table', $names->propertyName(new Expr\PropertyFetch(new Expr\Variable('this'), 'table')));
        self::assertNull($names->propertyName(new Expr\PropertyFetch(new Expr\Variable('other'), 'table')));
        self::assertNull($names->propertyName(new Expr\Variable('this')));
    }

    public function testArrowFunctionLeavesOutItsOwnParameters(): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php fn ($a) => $a . $b;')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\ArrowFunction::class, $statement->expr);

        self::assertSame(['b' => true], (new FreeNames())->arrowFunction($statement->expr));
    }

    public function testTargetReadsCountTheElementWrittenIntoButNotAReplacedVariable(): void
    {
        $names = new FreeNames();

        self::assertSame([], $names->targetReads(new Expr\Variable('sql')));
        self::assertSame(['rows' => true, 'key' => true], $names->targetReads(new Expr\ArrayDimFetch(new Expr\Variable('rows'), new Expr\Variable('key'))));
    }

    public function testSinkArgumentsOfNamesTheArgumentsADatabaseCallsResultDependsOn(): void
    {
        $names = new FreeNames(array_merge((new PdoExtension())->sinks(), (new WordPressExtension())->sinks()));
        $call = static fn (string $method): Expr\MethodCall => new Expr\MethodCall(new Expr\Variable('db'), $method);

        self::assertSame([0 => true], $names->sinkArgumentsOf($call('prepare')));
        self::assertSame([], $names->sinkArgumentsOf($call('query')));
        self::assertNull($names->sinkArgumentsOf($call('fetch')));
    }

    public function testSinkCallReadsOnlyWhatTheResultDependsOn(): void
    {
        $statement = (new SourceParser())->parse('t.php', '<?php $wpdb->prepare("(%s, %s)", $option, $value);')->statements[0];
        self::assertInstanceOf(Stmt\Expression::class, $statement);
        self::assertInstanceOf(Expr\MethodCall::class, $statement->expr);

        self::assertSame(['wpdb' => true], (new FreeNames())->sinkCall($statement->expr, [0 => true]));
    }
}
