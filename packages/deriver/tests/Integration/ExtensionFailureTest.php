<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Analyzer;
use Deriver\Exception\ModelException;
use Deriver\Model\CallModel;
use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Provider\DeclarationProvider;
use Deriver\Model\Provider\DispatchProvider;
use Deriver\Model\Provider\DomainProvider;
use Deriver\Model\Provider\EntryPointProvider;
use Deriver\Model\Provider\EnvironmentProvider;
use Deriver\Model\Provider\ObservationProvider;
use Deriver\Model\Provider\Provider;
use Deriver\Model\Provider\RefinementModel;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

#[CoversNothing]
#[Small]
final class ExtensionFailureTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenPropagatesDeclarationProviderFailures(): void
    {
        $provider = self::createStub(DeclarationProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new ModelException('Provider export failed');
        $provider->method('declarations')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new Analyzer())->open(new ProjectInput([]), new Configuration(providers: [$provider]));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenPropagatesEnvironmentProviderFailures(): void
    {
        $provider = self::createStub(EnvironmentProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new ModelException('Provider export failed');
        $provider->method('environment')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new Analyzer())->open(new ProjectInput([]), new Configuration(providers: [$provider]));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenPropagatesEntryPointProviderFailures(): void
    {
        $provider = self::createStub(EntryPointProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new ModelException('Provider export failed');
        $provider->method('entries')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new Analyzer())->open(new ProjectInput([]), new Configuration(providers: [$provider]));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenPropagatesDomainProviderFailures(): void
    {
        $provider = self::createStub(DomainProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new ModelException('Provider export failed');
        $provider->method('domains')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new Analyzer())->open(new ProjectInput([]), new Configuration(providers: [$provider]));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenPropagatesProviderIdentityFailures(): void
    {
        $provider = self::createStub(Provider::class);
        $failure = new ModelException('Provider implementation error');
        $provider->method('id')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new Analyzer())->open(new ProjectInput([]), new Configuration(providers: [$provider]));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenPropagatesModelDescriptorFailures(): void
    {
        $model = self::createStub(CallModel::class);
        $failure = new ModelException('Model descriptor implementation error');
        $model->method('descriptor')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new Analyzer())->open(new ProjectInput([]), new Configuration(models: [$model]));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenPropagatesIntrinsicDescriptorFailures(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $failure = new ModelException('Intrinsic descriptor implementation error');
        $intrinsic->method('descriptor')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new Analyzer())->open(new ProjectInput([]), new Configuration(intrinsics: [$intrinsic]));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenPropagatesDomainFailures(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $failure = new ModelException('Domain implementation error');
        $domain->method('bottom')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new Analyzer())->open(new ProjectInput([]), new Configuration(domains: [$domain]));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeriveCannotTurnAModelFailureIntoAnApplicationException(): void
    {
        $model = self::createStub(CallModel::class);
        $model->method('descriptor')->willReturn(new ModelDescriptor('broken', '1', 'external'));
        $failure = new ModelException('Model implementation error');
        $model->method('describe')->willThrowException($failure);
        $session = Analysis::session('<?php function target(){try{return external();}catch(Throwable $e){return "hidden";}}', new Configuration(models: [$model]));
        $this->expectExceptionObject($failure);
        $session->derive(new ReturnQuery('target'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testObservationsPropagateProviderFailures(): void
    {
        $provider = self::createStub(ObservationProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new ModelException('Cannot obtain observations');
        $provider->method('queries')->willThrowException($failure);
        $session = Analysis::session('<?php function target(){return 1;}', new Configuration(providers: [$provider]));
        $this->expectExceptionObject($failure);
        $session->observations();
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDerivePropagatesDispatchFailures(): void
    {
        $provider = self::createStub(DispatchProvider::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new ModelException('Dispatch implementation error');
        $provider->method('resolve')->willThrowException($failure);
        $session = Analysis::session('<?php interface Service {function value();} function target(Service $service){return $service->value();}', new Configuration(providers: [$provider]));
        $this->expectExceptionObject($failure);
        $session->derive(new ReturnQuery('target'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDerivePropagatesRefinementFailures(): void
    {
        $provider = self::createStub(RefinementModel::class);
        $provider->method('id')->willReturn('broken');
        $provider->method('version')->willReturn('1');
        $failure = new ModelException('Refinement implementation error');
        $provider->method('refine')->willThrowException($failure);
        $session = Analysis::session('<?php function target(bool $flag){return $flag ? 1 : 2;}', new Configuration(providers: [$provider]));
        $this->expectExceptionObject($failure);
        $session->derive(new ReturnQuery('target'));
    }
}
