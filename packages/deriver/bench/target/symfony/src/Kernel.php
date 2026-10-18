<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        if ($this->environment === 'prod' && strlen($_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? '') < 32) {
            throw new RuntimeException('APP_SECRET must contain at least 32 characters in production.');
        }
        parent::boot();
    }
}
