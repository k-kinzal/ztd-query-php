<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface SnapshotCompiler
{
    public function compile(string $source): Grammar;
}
