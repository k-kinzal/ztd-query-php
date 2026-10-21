<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ArgumentSpellingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\QueryRoot>}>
     */
    public static function providerQueries(): iterable
    {
        $cast = new M\Expression\Conversion\Cast(new M\Variable\UserVariable(new Name('input')), new M\Type\CastTarget(M\Type\Kind\CastKind::Decimal, '1'));
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $queries = [];
            foreach ([null, new Layout([new Spelled('', 'cast'), new Spelled('', '('), new Spelled('', '@'), new Spelled('', 'input'), new Spelled(' ', 'as'), new Spelled(' ', 'decimal'), new Spelled('', '('), new Spelled('', '1'), new Spelled('', ')'), new Spelled('', ')')]), new Layout([new Spelled('', 'CAST'), new Spelled('', '('), new Spelled(' ', '@'), new Spelled('', 'input'), new Spelled(' ', 'AS'), new Spelled(' ', 'DECIMAL'), new Spelled('', '('), new Spelled('', '1'), new Spelled('', ')'), new Spelled(' ', ')')])] as $layout) {
                $argument = new M\Call\CallArgument($cast, layout: $layout, spellingProfile: $layout === null ? null : new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::from('mysql-' . $release)));
                $queries[] = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Call\FunctionCall(new Name('ABS'), [$argument]), new Name('result'))]));
            }
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<M\QueryRoot> $queries The same directly constructed operands for every session value
     */
    #[DataProvider('providerQueries')]
    public function testArgumentNamesSurviveValueTransitionsWithoutInputSql(string $release, array $queries): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->set_charset('utf8mb4');
            $session = (new Instance($release))->connect();
            $session->query('SET NAMES utf8mb4');
            array_map(static function (string $setup) use ($native, $session, $queries): void {
                $node = $session->analyze($setup)->statement;
                self::assertInstanceOf(M\Statement::class, $node);
                self::assertSame(serialize((new Native())->observe($native, $setup)), serialize((new Memory())->observe($session, $node)), $setup);
                array_map(static function (M\QueryRoot $query) use ($native, $session, $setup): void {
                    $sql = (new Reference())->sql($query, $session->semantics()->profile());
                    $session->text = 'opaque non-SQL label';
                    self::assertSame(serialize((new Native())->observe($native, $sql)), serialize((new Memory())->observe($session, $query)), $setup . ': ' . $sql);
                    self::assertSame('opaque non-SQL label', $session->text);
                }, $queries);
            }, ['SET @input=NULL', "SET @input='1'", "SET @input='123'", "SET @input='-123'", "SET @input='abc'"]);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
