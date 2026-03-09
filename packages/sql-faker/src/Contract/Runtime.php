<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface Runtime
{
    public function snapshot(): Grammar;

    public function supportedGrammar(): Grammar;

    public function generate(GenerationRequest $request): string;
}
