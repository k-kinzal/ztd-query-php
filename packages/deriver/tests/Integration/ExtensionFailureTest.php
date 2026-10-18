<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\CallEvaluator;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchProvider;
use Deriver\Deriver\Evaluator\Call\Provider\Provider;
use Deriver\Deriver\Evaluator\Call\Provider\RefinementEvaluator;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Intrinsic\PureIntrinsic;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluator;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Providers\DeclarationProvider;
use Deriver\Deriver\Providers\DomainProvider;
use Deriver\Deriver\Providers\EntryPointProvider;
use Deriver\Deriver\Providers\EnvironmentProvider;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\EvaluationContractException;
use Deriver\Exception\EvaluationException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Small]
final class ExtensionFailureTest extends TestCase
{
    public function testOpenPropagatesDeclarationProviderFailures(): void
    {
        $provider = self::createStub(DeclarationProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new EvaluationException('Provider export failed');
        $provider->method('declarations')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        $prepared1Input = new Sources([]);
        $prepared1Configuration = new DeriverConfig();
        $prepared1Contributions = new Contributions(providers: [$provider]);
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
    }
    public function testOpenPropagatesEnvironmentProviderFailures(): void
    {
        $provider = self::createStub(EnvironmentProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new EvaluationException('Provider export failed');
        $provider->method('environment')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        $prepared2Input = new Sources([]);
        $prepared2Configuration = new DeriverConfig();
        $prepared2Contributions = new Contributions(providers: [$provider]);
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId = $prepared2Identity->id;
        $input = $prepared2Providers->input;
        $parser = new Parser(new ParserConfig($prepared2Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
    }
    public function testOpenPropagatesEntryPointProviderFailures(): void
    {
        $provider = self::createStub(EntryPointProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new EvaluationException('Provider export failed');
        $provider->method('entries')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        $prepared3Input = new Sources([]);
        $prepared3Configuration = new DeriverConfig();
        $prepared3Contributions = new Contributions(providers: [$provider]);
        $prepared3Target = new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision);
        $prepared3Providers = new ProviderExpansion($prepared3Input, array_map(Term::fromNative(...), $prepared3Configuration->environment), $prepared3Contributions);
        $contributions = $prepared3Providers->contributions;
        $prepared3Evaluators = new Evaluators(new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared3Configuration->standardEvaluators);
        $prepared3Settings = new EvaluatorConfig($prepared3Target, $prepared3Providers->environment, $prepared3Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared3Configuration->evaluators));
        $prepared3Identity = new SnapshotIdentity($prepared3Providers->input, $prepared3Settings, $prepared3Evaluators, $prepared3Providers->manifest);
        $snapshotId = $prepared3Identity->id;
        $input = $prepared3Providers->input;
        $parser = new Parser(new ParserConfig($prepared3Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared3Program = new Index($snapshotId, $trees);
        $prepared3Snapshot = $prepared3Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared3Program->diagnostics()));
    }
    public function testOpenPropagatesDomainProviderFailures(): void
    {
        $provider = self::createStub(DomainProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new EvaluationException('Provider export failed');
        $provider->method('domains')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        $prepared4Input = new Sources([]);
        $prepared4Configuration = new DeriverConfig();
        $prepared4Contributions = new Contributions(providers: [$provider]);
        $prepared4Target = new TargetProfile($prepared4Configuration->phpVersion, $prepared4Configuration->integerBits, $prepared4Configuration->floatPrecision);
        $prepared4Providers = new ProviderExpansion($prepared4Input, array_map(Term::fromNative(...), $prepared4Configuration->environment), $prepared4Contributions);
        $contributions = $prepared4Providers->contributions;
        $prepared4Evaluators = new Evaluators(new TargetProfile($prepared4Configuration->phpVersion, $prepared4Configuration->integerBits, $prepared4Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared4Configuration->standardEvaluators);
        $prepared4Settings = new EvaluatorConfig($prepared4Target, $prepared4Providers->environment, $prepared4Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared4Configuration->evaluators));
        $prepared4Identity = new SnapshotIdentity($prepared4Providers->input, $prepared4Settings, $prepared4Evaluators, $prepared4Providers->manifest);
        $snapshotId = $prepared4Identity->id;
        $input = $prepared4Providers->input;
        $parser = new Parser(new ParserConfig($prepared4Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared4Program = new Index($snapshotId, $trees);
        $prepared4Snapshot = $prepared4Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared4Program->diagnostics()));
    }
    public function testOpenPropagatesProviderIdentityFailures(): void
    {
        $provider = self::createStub(Provider::class);
        $failure = new EvaluationException('Provider implementation error');
        $provider->method('id')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        $prepared5Input = new Sources([]);
        $prepared5Configuration = new DeriverConfig();
        $prepared5Contributions = new Contributions(providers: [$provider]);
        $prepared5Target = new TargetProfile($prepared5Configuration->phpVersion, $prepared5Configuration->integerBits, $prepared5Configuration->floatPrecision);
        $prepared5Providers = new ProviderExpansion($prepared5Input, array_map(Term::fromNative(...), $prepared5Configuration->environment), $prepared5Contributions);
        $contributions = $prepared5Providers->contributions;
        $prepared5Evaluators = new Evaluators(new TargetProfile($prepared5Configuration->phpVersion, $prepared5Configuration->integerBits, $prepared5Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared5Configuration->standardEvaluators);
        $prepared5Settings = new EvaluatorConfig($prepared5Target, $prepared5Providers->environment, $prepared5Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared5Configuration->evaluators));
        $prepared5Identity = new SnapshotIdentity($prepared5Providers->input, $prepared5Settings, $prepared5Evaluators, $prepared5Providers->manifest);
        $snapshotId = $prepared5Identity->id;
        $input = $prepared5Providers->input;
        $parser = new Parser(new ParserConfig($prepared5Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared5Program = new Index($snapshotId, $trees);
        $prepared5Snapshot = $prepared5Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared5Program->diagnostics()));
    }
    public function testOpenPropagatesModelDescriptorFailures(): void
    {
        $evaluator = self::createStub(CallEvaluator::class);
        $failure = new EvaluationException('Evaluator descriptor implementation error');
        $evaluator->method('descriptor')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        $prepared6Input = new Sources([]);
        $prepared6Configuration = new DeriverConfig();
        $prepared6Contributions = new Contributions(evaluators: [$evaluator]);
        $prepared6Target = new TargetProfile($prepared6Configuration->phpVersion, $prepared6Configuration->integerBits, $prepared6Configuration->floatPrecision);
        $prepared6Providers = new ProviderExpansion($prepared6Input, array_map(Term::fromNative(...), $prepared6Configuration->environment), $prepared6Contributions);
        $contributions = $prepared6Providers->contributions;
        $prepared6Evaluators = new Evaluators(new TargetProfile($prepared6Configuration->phpVersion, $prepared6Configuration->integerBits, $prepared6Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared6Configuration->standardEvaluators);
        $prepared6Settings = new EvaluatorConfig($prepared6Target, $prepared6Providers->environment, $prepared6Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared6Configuration->evaluators));
        $prepared6Identity = new SnapshotIdentity($prepared6Providers->input, $prepared6Settings, $prepared6Evaluators, $prepared6Providers->manifest);
        $snapshotId = $prepared6Identity->id;
        $input = $prepared6Providers->input;
        $parser = new Parser(new ParserConfig($prepared6Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared6Program = new Index($snapshotId, $trees);
        $prepared6Snapshot = $prepared6Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared6Program->diagnostics()));
    }
    public function testOpenPropagatesIntrinsicDescriptorFailures(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $failure = new EvaluationException('Intrinsic descriptor implementation error');
        $intrinsic->method('descriptor')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        $prepared7Input = new Sources([]);
        $prepared7Configuration = new DeriverConfig();
        $prepared7Contributions = new Contributions(intrinsics: [$intrinsic]);
        $prepared7Target = new TargetProfile($prepared7Configuration->phpVersion, $prepared7Configuration->integerBits, $prepared7Configuration->floatPrecision);
        $prepared7Providers = new ProviderExpansion($prepared7Input, array_map(Term::fromNative(...), $prepared7Configuration->environment), $prepared7Contributions);
        $contributions = $prepared7Providers->contributions;
        $prepared7Evaluators = new Evaluators(new TargetProfile($prepared7Configuration->phpVersion, $prepared7Configuration->integerBits, $prepared7Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared7Configuration->standardEvaluators);
        $prepared7Settings = new EvaluatorConfig($prepared7Target, $prepared7Providers->environment, $prepared7Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared7Configuration->evaluators));
        $prepared7Identity = new SnapshotIdentity($prepared7Providers->input, $prepared7Settings, $prepared7Evaluators, $prepared7Providers->manifest);
        $snapshotId = $prepared7Identity->id;
        $input = $prepared7Providers->input;
        $parser = new Parser(new ParserConfig($prepared7Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared7Program = new Index($snapshotId, $trees);
        $prepared7Snapshot = $prepared7Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared7Program->diagnostics()));
    }
    public function testOpenPropagatesDomainFailures(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $failure = new EvaluationException('Domain implementation error');
        $domain->method('bottom')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        $prepared8Input = new Sources([]);
        $prepared8Configuration = new DeriverConfig();
        $prepared8Contributions = new Contributions(domains: [$domain]);
        $prepared8Target = new TargetProfile($prepared8Configuration->phpVersion, $prepared8Configuration->integerBits, $prepared8Configuration->floatPrecision);
        $prepared8Providers = new ProviderExpansion($prepared8Input, array_map(Term::fromNative(...), $prepared8Configuration->environment), $prepared8Contributions);
        $contributions = $prepared8Providers->contributions;
        $prepared8Evaluators = new Evaluators(new TargetProfile($prepared8Configuration->phpVersion, $prepared8Configuration->integerBits, $prepared8Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared8Configuration->standardEvaluators);
        $prepared8Settings = new EvaluatorConfig($prepared8Target, $prepared8Providers->environment, $prepared8Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared8Configuration->evaluators));
        $prepared8Identity = new SnapshotIdentity($prepared8Providers->input, $prepared8Settings, $prepared8Evaluators, $prepared8Providers->manifest);
        $snapshotId = $prepared8Identity->id;
        $input = $prepared8Providers->input;
        $parser = new Parser(new ParserConfig($prepared8Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared8Program = new Index($snapshotId, $trees);
        $prepared8Snapshot = $prepared8Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared8Program->diagnostics()));
    }
    public function testDeriveCannotTurnAModelFailureIntoAnApplicationException(): void
    {
        $evaluator = self::createStub(CallEvaluator::class);
        $evaluator->method('descriptor')->willReturn(new CallDescriptor('broken', '1', 'external'));
        $failure = new EvaluationException('Evaluator implementation error');
        $evaluator->method('evaluate')->willThrowException($failure);
        $input = new Sources([new Source('fixture.php', '<?php function target(){try{return external();}catch(Throwable $e){return "hidden";}}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions(evaluators: [$evaluator]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $this->expectExceptionObject($failure);
        $session->derive(new ReturnQuery('target'));
    }
    public function testMicroEvaluatorFailuresPropagateWithTheirIdentity(): void
    {
        $failure = new RuntimeException('Extension failed');
        $micro = (new MicroEvaluatorConversion())->convert(new MicroEvaluator('broken', '1', EvaluationOperation::Function, 'provided', static fn () => throw $failure));
        $target = new TargetProfile();
        $snapshotId = 'test';
        $input = new Sources([new Source('app.php', '<?php function target(){return provided();}')]);
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $program = new Index($snapshotId, $trees);
        $query = new CandidateQuery(new DefinitionIndex($program), new EvaluatorConfig($target, evaluators: [$micro]), new Evaluators($target), new Snapshot('test', [], [], $target, ''));
        $this->expectException(EvaluationContractException::class);
        $this->expectExceptionMessage('broken: Extension failed');
        $query->derive(new ReturnQuery('target'));
    }
    public function testDerivePropagatesDispatchFailures(): void
    {
        $provider = self::createStub(DispatchProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new EvaluationException('Dispatch implementation error');
        $provider->method('resolve')->willThrowException($failure);
        $input = new Sources([new Source('fixture.php', '<?php interface Service {function value();} function target(Service $service){return $service->value();}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions(providers: [$provider]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $this->expectExceptionObject($failure);
        $session->derive(new ReturnQuery('target'));
    }
    public function testDerivePropagatesRefinementFailures(): void
    {
        $provider = self::createStub(RefinementEvaluator::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new EvaluationException('Refinement implementation error');
        $provider->method('refine')->willThrowException($failure);
        $input = new Sources([new Source('fixture.php', '<?php function target(bool $flag){return $flag ? 1 : 2;}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions(providers: [$provider]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $this->expectExceptionObject($failure);
        $session->derive(new ReturnQuery('target'));
    }
}
