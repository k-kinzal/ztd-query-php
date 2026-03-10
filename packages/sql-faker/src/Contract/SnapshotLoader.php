<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface SnapshotLoader
{
    public function load(): Grammar;
}
