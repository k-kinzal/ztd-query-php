<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\StatementGenerator as StatementGeneratorContract;
use SqlFaker\Generation\GenerationRuntime;

final class StatementGenerator implements StatementGeneratorContract
{
    private GenerationRuntime $runtime;

    public function __construct(
        FakerGenerator $faker,
        ?string $version = null,
        ?LexicalValueSource $lexicalValues = null,
        ?GenerationRuntime $runtime = null,
    ) {
        $this->runtime = $runtime ?? RuntimeFactory::build($faker, $version, $lexicalValues);
    }

    public function generate(GenerationRequest $request): string
    {
        return $this->runtime->generate($request);
    }
}
