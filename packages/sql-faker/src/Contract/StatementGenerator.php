<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface StatementGenerator
{
    public function generate(GenerationRequest $request): string;
}
