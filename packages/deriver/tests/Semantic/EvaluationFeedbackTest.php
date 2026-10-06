<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Project\Configuration;
use Deriver\Query\Budget;
use Deriver\Query\ResourceLimits;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Candidates;

/**
 * Consumer regressions retain useful candidates without inventing mutable input facts.
 */
#[CoversNothing]
#[Medium]
final class EvaluationFeedbackTest extends TestCase
{
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testSignedLargeArrayMatchesItsNumericStringSpellingUnderDefaultLimits(): void
    {
        $suffix = implode(',', range(1, 8191));
        $signed = Analysis::returns('<?php function target(){return [-1=>0,' . $suffix . '];}');
        $string = Analysis::returns('<?php function target(){return ["-1"=>0,' . $suffix . '];}');
        $expected = array_combine(range(-1, 8190), range(0, 8191));
        self::assertNotNull($signed->definite());
        self::assertNotNull($string->definite());
        self::assertSame($expected, $signed->definite()->values['return']->native());
        self::assertSame($expected, $string->definite()->values['return']->native());
        self::assertLessThan(100, $signed->statistics->transfers);
    }

    /**
     * @param string $prefix Source before the observation
     * @param string $parameter Parameter declaration
     * @param string $type Sound recovered bound
     * @throws JsonException If source metadata cannot be encoded
     */
    #[DataProvider('bindings')]
    public function testInterruptedRecoveryUsesOnlyImmutableParameterTypes(string $prefix, string $parameter, string $type): void
    {
        $session = Analysis::session('<?php function target(' . $parameter . ',array $in){' . $prefix . '$ids=heavy($in);$pdo->query("SELECT n = ".count($ids));}');
        $site = $session->callsTo('query')[0];
        self::assertNotNull($site->receiver);
        $query = new TupleQuery($site->beforeInvocation(), ['receiver' => $site->receiver, 'sql' => $site->argument(0)], budget: new Budget(transfers: 1));
        $result = $session->derive($query);
        self::assertSame($type, $result->normalOutcomes[0]->values['receiver']->attributes['type']);
        self::assertSame('SELECT n = ', Candidates::prefix($result->normalOutcomes[0]->values['sql']));
        self::assertContains('BUDGET_EXCEEDED', array_column($result->frontiers, 'code'));
        self::assertNull($result->definite());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function bindings(): iterable
    {
        yield 'unmodified' => ['', 'PDO $pdo', 'PDO'];
        yield 'nullable union' => ['', 'PDO|Other|null $pdo', 'PDO|Other|null'];
        yield 'reassigned' => ['$pdo=new Other;', 'PDO $pdo', 'mixed'];
        yield 'reference entry' => ['', 'PDO &$pdo', 'mixed'];
        yield 'passed address' => ['change($pdo);', 'PDO $pdo', 'mixed'];
        yield 'alias' => ['$alias=&$pdo;change($alias);', 'PDO $pdo', 'mixed'];
        yield 'unset' => ['unset($pdo);', 'PDO $pdo', 'mixed'];
        yield 'variable variables' => ['$$in[0]=1;', 'PDO $pdo', 'mixed'];
        yield 'extract' => ['extract($in);', 'PDO $pdo', 'mixed'];
        yield 'dynamic extract' => ['$f="extract";$f($in);', 'PDO $pdo', 'mixed'];
        yield 'include' => ['include "unknown.php";', 'PDO $pdo', 'mixed'];
        yield 'reference closure' => ['$f=function()use(&$pdo){$pdo=null;};$f();', 'PDO $pdo', 'mixed'];
        yield 'catch rebinding' => ['try{heavy();}catch(Throwable $pdo){}', 'PDO $pdo', 'mixed'];
        yield 'global rebinding' => ['global $pdo;', 'PDO $pdo', 'mixed'];
        yield 'static rebinding' => ['static $pdo=null;', 'PDO $pdo', 'mixed'];
        yield 'untyped' => ['', '$pdo', 'mixed'];
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testTimeInterruptionRetainsAReceiverTypeWithoutClaimingExecution(): void
    {
        $session = Analysis::session('<?php function target(PDO $pdo,array $in){$ids=heavy($in);$pdo->query("SELECT n = ".count($ids));}', new Configuration(resources: new ResourceLimits(seconds: 0.000000001)));
        $site = $session->callsTo('query')[0];
        self::assertNotNull($site->receiver);
        $result = $session->derive(new TupleQuery($site->beforeInvocation(), ['receiver' => $site->receiver, 'sql' => $site->argument(0)]));
        self::assertSame('PDO', $result->normalOutcomes[0]->values['receiver']->attributes['type']);
        self::assertSame('SELECT n = ', Candidates::prefix($result->normalOutcomes[0]->values['sql']));
        self::assertContains('TIME_LIMIT', array_column($result->frontiers, 'code'));
        self::assertSame('open', $result->assessment->closure);
        self::assertNull($result->definite());
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnUnrelatedFrontierDoesNotBlockClosedHelperSharing(): void
    {
        $session = Analysis::session('<?php function helper($x){return $x."users";} function sink($x){} function target(){external();$sql=helper("SELECT ");sink($sql);sink($sql." WHERE id=1");}');
        $sites = $session->callsTo('sink');
        $first = $session->derive(new ValueQuery($sites[0]->argument(0)));
        $second = $session->derive(new ValueQuery($sites[1]->argument(0)));
        self::assertSame('SELECT users', $first->normalOutcomes[0]->values['value']->native());
        self::assertSame('SELECT users WHERE id=1', $second->normalOutcomes[0]->values['value']->native());
        self::assertNotEmpty($first->frontiers);
        self::assertSame(array_column($first->frontiers, 'code'), array_column($second->frontiers, 'code'));
        self::assertGreaterThan(0, $second->statistics->cacheHits);
        self::assertNull($second->definite());
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testWarningBearingHelperIsNeverPublishedAsClosed(): void
    {
        $source = '<?php function helper(){return $missing;} function sink($x){} function target(){external();$v=helper();sink($v);sink($v);}';
        $session = Analysis::session($source);
        $sites = $session->callsTo('sink');
        $session->derive(new ValueQuery($sites[0]->argument(0)));
        $warm = $session->derive(new ValueQuery($sites[1]->argument(0)));
        $coldSession = Analysis::session($source);
        $cold = $coldSession->derive(new ValueQuery($coldSession->callsTo('sink')[1]->argument(0)));
        self::assertEquals($cold->frontiers, $warm->frontiers);
        self::assertContains('PHP_WARNING', array_column($warm->frontiers, 'code'));
        self::assertNull($warm->definite());
    }
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testLossyFloatKeysRemainWarningBearingAfterLiteralFallback(): void
    {
        $result = Analysis::returns('<?php function target(){return [-1.5=>1,2];}');
        self::assertSame([-1 => 1,0 => 2], $result->normalOutcomes[0]->values['return']->native());
        self::assertContains('PHP_WARNING', array_column($result->frontiers, 'code'));
        self::assertNull($result->definite());
    }

}
