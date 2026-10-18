<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Provider;

use Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchProvider;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchRequest;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchTarget;
use Deriver\Deriver\Evaluator\Call\Provider\Provider;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Providers\DeclarationProvider;
use Deriver\Deriver\Providers\EntryPointProvider;
use Deriver\Deriver\Providers\EnvironmentProvider;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Provider\Provider
 */
#[CoversClass(Provider::class)]
#[Small]
final class ProviderTest extends TestCase
{
    public function testIdNamesTheCapturedExport(): void
    {
        self::assertSame('example.container', (new class () implements DeclarationProvider, DispatchProvider, EnvironmentProvider, EntryPointProvider {
            /**
             * @return string Provider identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.container';
            }
            /**
             * @return string Captured export version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return Sources Explicit generated declaration and semantics
             */
            #[Override]
            public function declarations(): Sources
            {
                return new Sources([new Source('generated/service.php', '<?php class GeneratedService{public function label(){return "service";}}')]);
            }
            /**
             * @param DispatchRequest $request Evaluated call metadata
             * @return DispatchDecision Exported binding, with explicit completeness
             */
            #[Override]
            public function resolve(DispatchRequest $request): DispatchDecision
            {
                if ($request->method !== 'label' || ($request->receiver->attributes['type'] ?? '') !== 'Contract') {
                    return new DispatchDecision();
                }
                return new DispatchDecision([new DispatchTarget('GeneratedService::label', new Term(TermKind::Object, 'container:shared-service', attributes: ['class' => 'GeneratedService']))], true, ['container:export-complete']);
            }
            /**
             * @return array<string, Term> Captured settings
             */
            #[Override]
            public function environment(): array
            {
                return ['APP_NAME' => Term::constant('captured')];
            }
            /**
             * @return list<EntryPoint> Declared lifecycle entries
             */
            #[Override]
            public function entries(): array
            {
                return [new EntryPoint('entry')];
            }
        })->id());
    }
    public function testVersionIdentifiesTheCapturedExport(): void
    {
        self::assertSame('1', (new class () implements DeclarationProvider, DispatchProvider, EnvironmentProvider, EntryPointProvider {
            /**
             * @return string Provider identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.container';
            }
            /**
             * @return string Captured export version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return Sources Explicit generated declaration and semantics
             */
            #[Override]
            public function declarations(): Sources
            {
                return new Sources([new Source('generated/service.php', '<?php class GeneratedService{public function label(){return "service";}}')]);
            }
            /**
             * @param DispatchRequest $request Evaluated call metadata
             * @return DispatchDecision Exported binding, with explicit completeness
             */
            #[Override]
            public function resolve(DispatchRequest $request): DispatchDecision
            {
                if ($request->method !== 'label' || ($request->receiver->attributes['type'] ?? '') !== 'Contract') {
                    return new DispatchDecision();
                }
                return new DispatchDecision([new DispatchTarget('GeneratedService::label', new Term(TermKind::Object, 'container:shared-service', attributes: ['class' => 'GeneratedService']))], true, ['container:export-complete']);
            }
            /**
             * @return array<string, Term> Captured settings
             */
            #[Override]
            public function environment(): array
            {
                return ['APP_NAME' => Term::constant('captured')];
            }
            /**
             * @return list<EntryPoint> Declared lifecycle entries
             */
            #[Override]
            public function entries(): array
            {
                return [new EntryPoint('entry')];
            }
        })->version());
    }
}
