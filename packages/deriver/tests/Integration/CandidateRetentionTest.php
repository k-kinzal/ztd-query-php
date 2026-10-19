<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver;
use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Sources\Path;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Source forms whose candidates used to disappear or to be decided wrongly, each derived through the public API.
 */
#[CoversNothing]
#[Medium]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CandidateRetentionTest extends TestCase
{
    private vfsStreamDirectory $filesystem;

    /**
     * @throws \phpmock\MockEnabledException
     */
    protected function setUp(): void
    {
        $workingDirectory = '/project';
        $this->filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use ($workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use ($workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use ($workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use ($workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
    }

    public function testBinaryOperatorsCombineACallWithAVariableRead(): void
    {
        $deriver = $this->prepare(['app.php' => '<?php function text($s){} function number($n){} function up($v){return strtoupper($v);}
            class Model{public $pk="k";public function g(){return "g";}}
            function literal(){$a="x";text(strtoupper($a)." - ".$a);}
            function twoVariables(){$a="x";$b="y";text($b.up($a));}
            function arithmetic(){$a="xy";number(strlen($a)+strlen($a));}
            function property(){$o=new Model();text($o->g().$o->pk);}
            function symbolic($a){return strtoupper($a).$a;}']);
        self::assertSame(['X - x', 'gk', 'yX'], $this->values($deriver->derive('text#parameter(s)')));
        self::assertSame([4], $this->values($deriver->derive('number#parameter(n)')));
        $symbolic = $deriver->derive('symbolic#return');
        self::assertCount(1, $symbolic);
        self::assertSame(CandidateStatus::Partials, $symbolic[0]->type);
    }

    public function testGroupedCaseLabelsKeepTheSharedAssignment(): void
    {
        $deriver = $this->prepare(['app.php' => '<?php function sink($s){} function run($type){switch($type){case "post":case "page":$o="title";break;default:$o="id";}sink($o);}']);
        self::assertSame(['id', 'title'], $this->values($deriver->derive('sink#parameter(s)')));
        self::assertSame(['id', 'title'], $this->values(Deriver::prepare([$this->path('app.php')], new DeriverConfig(evaluators: BranchEvaluators::truth()))->derive('sink#parameter(s)')));
    }

    public function testReceiversWithoutAClassKeepTheArgumentBehindAnUnresolvedDispatch(): void
    {
        $deriver = $this->prepare(['app.php' => '<?php class DB{public function query($sql){}}
            function untyped($db){$db->query("UNTYPED");}
            class Holder{public $db;function run(){$this->db->query("PROPERTY");}}
            function nullsafe(?DB $db){$db?->query("NULLSAFE");}
            function never(){global $never;$never->query("GLOBAL");}
            function typed(DB $db){$db->query("TYPED");}']);
        $result = $deriver->derive('DB::query#parameter(sql)');
        $analyzed = [];
        $unresolved = [];
        foreach ($result as $candidate) {
            if ($candidate->result instanceof Term) {
                self::assertSame(TermKind::ControlledValue, $candidate->result->kind);
                self::assertSame('UNRESOLVED_DISPATCH', $candidate->result->attributes['reason']);
                $unresolved[] = $candidate->result->operands[0]->literal;
            } else {
                $analyzed[] = $candidate->result;
            }
        }
        sort($analyzed);
        sort($unresolved);
        self::assertSame(['NULLSAFE', 'TYPED'], $analyzed);
        self::assertSame(['GLOBAL', 'PROPERTY', 'UNTYPED'], $unresolved);
    }

    public function testExplicitConstructorCallsAreConstructorCallers(): void
    {
        $deriver = $this->prepare(['app.php' => '<?php class Base{public function __construct($name){}} class Child extends Base{public function __construct(){parent::__construct("child");}} class Other extends Base{public static function make(){return new static("made");}} function direct(){new Base("direct");}']);
        self::assertSame(['child', 'direct', 'made'], $this->values($deriver->derive('Base::__construct#parameter(name)')));
    }

    public function testEveryCallThroughAGlobalReceiverIsObserved(): void
    {
        $deriver = $this->prepare([
            'wpdb.php' => '<?php class wpdb{public function get_var($query){return 1;} public function chain($query){return $this;}} $wpdb=new wpdb(); $wpdb->chain("FIRST")->chain("CHAINED");$wpdb->chain("SECOND");',
            'app.php' => '<?php function f(){global $wpdb;$wpdb->get_var("A");$r=$wpdb->get_var("B");$wpdb->get_var("C".$r);}',
        ]);
        $values = $this->values($deriver->derive('wpdb::get_var#parameter(query)'));
        self::assertSame(['A', 'B'], array_slice($values, 0, 2));
        self::assertCount(3, $values);
        self::assertSame(['CHAINED', 'FIRST', 'SECOND'], $this->values($deriver->derive('wpdb::chain#parameter(query)')));
    }

    public function testUnavailableCalleesLeaveGlobalsTheyWereNotGivenIntact(): void
    {
        $deriver = $this->prepare([
            'wpdb.php' => '<?php class wpdb{public function get_var($query){}} $wpdb=new wpdb(); $config=["table"=>"posts"];',
            'app.php' => '<?php function f(){global $wpdb,$config;undefined_fn();$wpdb->get_var("SELECT ".$config["table"]);}',
        ]);
        self::assertSame(['SELECT posts'], $this->values($deriver->derive('wpdb::get_var#parameter(query)')));
    }

    public function testDeclaredTypesDecideStrictComparisonsAndInstanceof(): void
    {
        $sources = ['app.php' => '<?php function sink($s){}
            function nullCheck(string $s){sink($s===null?"null":"string");}
            function implicitNullable(string $s=null){sink($s===null?"maybe null":"maybe string");}
            function closure(){$f=fn()=>1;sink($f instanceof \Closure?"closure":"other");}
            function scalar(int $n){sink($n instanceof \Closure?"closure":"int");}'];
        $this->prepare($sources);
        $pruned = Deriver::prepare([$this->path('app.php')], new DeriverConfig(evaluators: BranchEvaluators::truth()));
        self::assertSame(['closure', 'int', 'maybe null', 'maybe string', 'string'], $this->values($pruned->derive('sink#parameter(s)')));
    }

    public function testUndefinedLocalsReadAsNullInsideFunctions(): void
    {
        $sources = ['app.php' => '<?php function sink($s){} function undefined(){sink($nothing);} function check(){sink(isset($nothing)?"set":"unset");} function dynamic($name){$$name=1;sink($nothing);}'];
        $this->prepare($sources);
        $pruned = Deriver::prepare([$this->path('app.php')], new DeriverConfig(evaluators: BranchEvaluators::truth()));
        $result = $pruned->derive('sink#parameter(s)');
        $partials = array_values(array_filter(iterator_to_array($result, false), static fn ($candidate): bool => $candidate->result instanceof Term));
        self::assertCount(1, $partials);
        self::assertSame([null, 'unset'], $this->values($result));
    }

    /**
     * @param array<string, string> $files File name to contents
     */
    private function prepare(array $files): Deriver
    {
        $paths = [];
        foreach ($files as $name => $contents) {
            file_put_contents($this->filesystem->url() . '/' . $name, $contents);
            $paths[] = $this->path($name);
        }
        return Deriver::prepare($paths);
    }

    private function path(string $name): string
    {
        return '/' . $this->filesystem->path() . '/' . $name;
    }

    /**
     * @return list<mixed> Concrete results in sorted order; partial candidates are skipped
     */
    private function values(CandidateCollection $candidates): array
    {
        $values = [];
        foreach ($candidates as $candidate) {
            if (!$candidate->result instanceof Term) {
                $values[] = $candidate->result;
            }
        }
        sort($values);
        return $values;
    }
}
