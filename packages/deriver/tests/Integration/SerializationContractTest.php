<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\ValueReader;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

#[CoversNothing]
#[Small]
final class SerializationContractTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSecretEntriesAndDerivedReturnsAreRedactedByDefault(): void
    {
        $session = Analysis::session('<?php function target($secret){return "prefix:".$secret;}');
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('confidential-token', true)])]));
        $result = $session->derive($query);
        $safe = $result->toJson();
        $revealed = $result->toJson(true);
        self::assertStringNotContainsString(base64_encode('confidential-token'), $safe);
        self::assertStringNotContainsString(base64_encode('prefix:confidential-token'), $safe);
        self::assertStringContainsString('"redacted": true', $safe);
        self::assertStringContainsString(base64_encode('prefix:confidential-token'), $revealed);
        self::assertStringContainsString('"scope"', $safe);
        self::assertStringContainsString('"budget"', $safe);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testNonUtf8BytesAndFloatingPointSpecialValuesHaveLosslessTags(): void
    {
        $session = Analysis::session('<?php function target($input){return $input;}');
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::array(['bytes' => Term::constant("\xff\x00"), 'infinity' => Term::constant(INF), 'negative-zero' => Term::constant(-0.0)])])]));
        $json = $session->derive($query)->toJson();
        self::assertJson($json);
        self::assertStringContainsString(base64_encode("\xff\x00"), $json);
        self::assertStringContainsString('7ff0000000000000', $json);
        self::assertStringContainsString('8000000000000000', $json);
    }
    /**
     * @throws JsonException If lossless result encoding fails
     */
    public function testNonUtf8IdentifierMetadataRoundTripsWithoutReplacement(): void
    {
        $result = Analysis::returns("<?php function target(){return \xe8;}");
        $json = json_decode($result->toJson(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertStringContainsString('~b64~' . base64_encode("constant:\xe8"), $result->toJson());
        self::assertSame("constant:\xe8", (new JsonText())->decode('~b64~' . base64_encode("constant:\xe8")));
    }

    /**
     * @throws JsonException If the lossless report cannot be encoded
     */
    public function testStorageGraphPreservesReturnedObjectCyclesAndLocalAliases(): void
    {
        $result = Analysis::returns('<?php class B{public ?B $self=null;public int $value=3;} function target(){$b=new B;$alias=$b;$b->self=$b;$x=1;$y=&$x;return $b;}');
        $outcome = $result->normalOutcomes[0];
        $identity = $outcome->values['return']->literal;
        self::assertIsString($identity);
        self::assertSame($identity, $outcome->storage->cells['object:'.$identity]->operands['self']->literal);
        self::assertSame(3, $outcome->storage->cells['object:'.$identity]->operands['value']->native());
        self::assertSame($outcome->storage->bindings['x']->literal, $outcome->storage->bindings['y']->literal);
        self::assertTrue(\Tests\Fake\ReportSchema::accepts($result->toJson()));
        $reader = ValueReader::fromJson($result->toJson());
        self::assertSame('object', $reader->read('v0')->kind);
    }

    /**
     * @throws JsonException If the lossless report cannot be encoded
     */
    public function testExceptionalStorageIncludesPropertiesWrittenBeforeTheThrow(): void
    {
        $result = Analysis::returns('<?php class B{public int $value=1;} function target(){$b=new B;$b->value=4;throw new Error("failure");}');
        $outcome = $result->exceptionalOutcomes[0];
        $identity = $outcome->state['b']->literal;
        self::assertIsString($identity);
        self::assertSame(4, $outcome->storage->cells['object:'.$identity]->operands['value']->native());
        self::assertNotEmpty($outcome->evidence);
        self::assertTrue(\Tests\Fake\ReportSchema::accepts($result->toJson()));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCustomIntrinsicResultsRetainSecretInputRedaction(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('example.private', '1', 'example.private', 1, [0]));
        $intrinsic->method('evaluate')->willReturn(Term::constant('derived-confidential-value'));
        $signature = new Signature([new Parameter('value', 'string')]);
        $plan = new SemanticPlan([Action::returns(new Expression('intrinsic', 'example.private', [Expression::parameter('value')]))]);
        $model = new \Tests\Fake\PlanModel(new ModelDescriptor('example.private', '1', 'derive_private', $signature), $plan);
        $session = Analysis::session('<?php function target(string $input){return derive_private($input);}', new Configuration(models:[$model], intrinsics:[$intrinsic]));
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('private-input', true)])])));
        self::assertSame([], $result->frontiers);
        self::assertTrue($result->normalOutcomes[0]->values['return']->isSecret());
        self::assertStringNotContainsString(base64_encode('derived-confidential-value'), $result->toJson());
        self::assertStringContainsString(base64_encode('derived-confidential-value'), $result->toJson(true));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDerivedSecretSelection')]
    public function testDerivedSecretSelectionRemainsRedacted(string $body, string $expected): void
    {
        $session = Analysis::session('<?php function target(string $input){'.$body.'}');
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('xx', true)])])));
        self::assertSame([], $result->frontiers);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
        self::assertTrue($result->normalOutcomes[0]->values['return']->isSecret());
        self::assertStringContainsString('"redacted": true', $result->toJson());
        self::assertStringNotContainsString(base64_encode($expected), $result->toJson());
        self::assertStringContainsString(base64_encode($expected), $result->toJson(true));
    }
    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerDerivedSecretSelection(): iterable
    {
        yield 'filter predicate selection' => ['return array_values(array_filter(["selected"],fn($value)=>$input==="xx"))[0];','selected'];
        yield 'replacement result' => ['return str_replace("x","y",$input);','yy'];
        yield 'array keys' => ['return array_keys([$input=>1])[0];','xx'];
        yield 'stored array keys' => ['$a=array_keys([$input=>1]);return $a[0];','xx'];
        yield 'secret lookup key' => ['return ["xx"=>"selected"][$input];','selected'];
        yield 'foreach key' => ['foreach([$input=>1] as $key=>$value){return $key;}return "missing";','xx'];
        yield 'foreach value' => ['foreach(array_keys([$input=>1]) as $value){return $value;}return "missing";','xx'];
        yield 'foreach reference' => ['$a=array_keys([$input=>1]);foreach($a as &$value){return $value;}return "missing";','xx'];
        yield 'destructuring' => ['[$value]=array_keys([$input=>1]);return $value;','xx'];
        yield 'reference' => ['$a=array_keys([$input=>1]);$value=&$a[0];return $value;','xx'];
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCustomAggregateSelection')]
    public function testCustomAggregateSelectionsRetainSecretInputRedaction(string $body): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('example.private', '1', 'example.private', 1, [0]));
        $intrinsic->method('evaluate')->willReturn(Term::fromNative(['keep' => 'derived-confidential-value','drop' => 'discarded']));
        $signature = new Signature([new Parameter('value', 'string')]);
        $plan = new SemanticPlan([Action::returns(new Expression('intrinsic', 'example.private', [Expression::parameter('value')]))]);
        $model = new \Tests\Fake\PlanModel(new ModelDescriptor('example.private', '1', 'derive_private', $signature), $plan);
        $session = Analysis::session('<?php function target(string $input){$a=derive_private($input);'.$body.'}', new Configuration(models:[$model], intrinsics:[$intrinsic]));
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('private-input', true)])])));
        self::assertSame([], $result->frontiers);
        self::assertTrue($result->normalOutcomes[0]->values['return']->isSecret());
        self::assertStringNotContainsString(base64_encode('derived-confidential-value'), $result->toJson());
        self::assertStringContainsString(base64_encode('derived-confidential-value'), $result->toJson(true));
    }
    /**
     * @return iterable<string,array{string}>
     */
    public static function providerCustomAggregateSelection(): iterable
    {
        yield 'offset' => ['return $a["keep"];'];
        yield 'map identity' => ['return array_map(null,$a);'];
        yield 'map callback' => ['return array_map(fn($value)=>$value,$a);'];
        yield 'map element selection' => ['return array_map(null,$a)["keep"];'];
        yield 'filter default' => ['return array_filter($a);'];
        yield 'filter callback' => ['return array_filter($a,fn($value)=>true);'];
        yield 'filter element selection' => ['return array_filter($a)["keep"];'];
        yield 'reduce callback' => ['return array_reduce($a,fn($carry,$value)=>$carry??$value,null);'];

        yield 'unset' => ['unset($a["drop"]);return $a;'];
        yield 'reference' => ['$value=&$a["keep"];return $value;'];
        yield 'foreach value' => ['foreach($a as $value){return $value;}return null;'];
        yield 'foreach reference' => ['foreach($a as &$value){return $value;}return null;'];
    }


    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerConfidentialExistence')]
    public function testExistenceResultsRetainConfidentialInputLabels(string $body): void
    {
        $session = Analysis::session('<?php function target(string $input){'.$body.'}');
        $result = $session->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant('xx', true)])])));
        self::assertSame([], $result->frontiers);
        self::assertFalse($result->normalOutcomes[0]->values['return']->native());
        self::assertTrue($result->normalOutcomes[0]->values['return']->isSecret());
        self::assertStringContainsString('"redacted": true', $result->toJson());
    }
    /**
     * @return iterable<string,array{string}>
     */
    public static function providerConfidentialExistence(): iterable
    {
        yield 'secret string length' => ['return isset($input[3]);'];
        yield 'secret invalid string key' => ['$s="abc";return isset($s[$input]);'];
        yield 'secret ternary' => ['return $input === "xx" ? false : true;'];
        yield 'secret short circuit' => ['return $input === "yy" && true;'];
        yield 'secret absent array key' => ['$a=["public"=>1];return isset($a[$input]);'];
    }
}
