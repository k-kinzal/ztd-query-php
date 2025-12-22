<?php

declare(strict_types=1);

namespace Tests\Fixture;

final class ForbiddenMagicMethodCallFixture
{
    public function __toString(): string
    {
        return 'fixture';
    }

    public function __clone(): void
    {
    }

    public function directMagicCalls(): void
    {
        $obj = new self();
        $obj->__toString();
        $obj->__clone();
    }

    public function regularMethodCalls(): void
    {
        $obj = new self();
        $result = (string) $obj;
        unset($result);
    }
}

final class ChildFixture extends ForbiddenMagicMethodCallFixture
{
    public function __toString(): string
    {
        return parent::__toString() . ' child';
    }

    public function __clone(): void
    {
        parent::__clone();
    }

    public function directCallFromChild(): void
    {
        $this->__toString();
    }
}

final class StaticMagicCallFixture
{
    public static function __callStatic(string $name, array $arguments): void
    {
        unset($name, $arguments);
    }

    public static function callStaticDirectly(): void
    {
        self::__callStatic('foo', []);
    }
}
