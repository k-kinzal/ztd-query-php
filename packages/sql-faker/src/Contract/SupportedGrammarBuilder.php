<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface SupportedGrammarBuilder
{
    public function build(Grammar $snapshot): Grammar;
}
