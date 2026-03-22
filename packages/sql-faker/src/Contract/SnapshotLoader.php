<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface SnapshotLoader
{
    public function version(): string;

    public function load(): Grammar;
}
