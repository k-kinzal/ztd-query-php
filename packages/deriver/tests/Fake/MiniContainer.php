<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\AnalysisSession;
use Deriver\Model\Provider\DeclarationProvider;
use Deriver\Model\Provider\DispatchDecision;
use Deriver\Model\Provider\DispatchProvider;
use Deriver\Model\Provider\DispatchRequest;
use Deriver\Model\Provider\DispatchTarget;
use Deriver\Model\Provider\EntryPointProvider;
use Deriver\Model\Provider\EnvironmentProvider;
use Deriver\Model\Provider\ObservationProvider;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\Query;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use Override;

/**
 * Captured container export; all source, settings, entries, and dispatch are data.
 * @visibility root
 */
final class MiniContainer implements DeclarationProvider, DispatchProvider, EnvironmentProvider, EntryPointProvider, ObservationProvider
{
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
     * @return ProjectInput Explicit generated declaration and semantics
     */
    #[Override]
    public function declarations(): ProjectInput
    {
        return new ProjectInput([new SourceFile('generated/service.php', '<?php class GeneratedService{public function label(){return "service";}}')]);
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
        return new DispatchDecision([new DispatchTarget('GeneratedService::label', new Term('object', 'container:shared-service', attributes: ['class' => 'GeneratedService']))], true, ['container:export-complete']);
    }
    /**
     * @return array<string, Term> Captured settings
     */
    #[Override]
    public function environment(): array
    {
        return ['env:APP_NAME' => Term::constant('captured')];
    }
    /**
     * @return list<EntryPoint> Declared lifecycle entries
     */
    #[Override]
    public function entries(): array
    {
        return [new EntryPoint('entry')];
    }
    /**
     * @param AnalysisSession $session Captured project
     * @return array<string, Query> Named observations
     */
    #[Override]
    public function queries(AnalysisSession $session): array
    {
        return ['entry-return' => new ReturnQuery('entry', QueryScope::fromEntrypoints($session->entrypoints()))];
    }
}
