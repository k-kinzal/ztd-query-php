<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * Where in the program a statement is issued, read out of the enclosing function's name.
 *
 * The catalog writes the enclosing function as `Class::method`, `function`
 * or `{main}`. The report needs the pieces: the namespace to group by, the
 * class to write a page for, and the member to head a section with. Reading
 * them once here is what keeps every page addressing the same code the same
 * way.
 *
 * @visibility root
 */
final class Scope
{
    /**
     * The name the catalog gives to code outside any function.
     */
    public const MAIN = '{main}';

    /**
     * @param string $namespace The namespace, empty for the global one
     * @param string|null $class The fully qualified class, or null outside a class
     * @param string $member The method, function or `{main}`
     */
    public function __construct(
        public readonly string $namespace,
        public readonly ?string $class,
        public readonly string $member,
    ) {
    }

    /**
     * The scope an enclosing function's name stands for.
     */
    public static function of(string $function): self
    {
        $function = ltrim($function, '\\');
        $separator = strpos($function, '::');
        if ($separator !== false) {
            $class = substr($function, 0, $separator);
            $slash = strrpos($class, '\\');

            return new self($slash === false ? '' : substr($class, 0, $slash), $class, substr($function, $separator + 2));
        }
        $slash = strrpos($function, '\\');

        return new self($slash === false ? '' : substr($function, 0, $slash), null, $slash === false ? $function : substr($function, $slash + 1));
    }

    /**
     * Whether the statement is issued outside any function.
     */
    public function isMain(): bool
    {
        return $this->class === null && $this->member === self::MAIN;
    }

    /**
     * Whether the statement is issued from a method.
     */
    public function isMethod(): bool
    {
        return $this->class !== null;
    }

    /**
     * The class without its namespace, or null outside a class.
     */
    public function classShort(): ?string
    {
        if ($this->class === null) {
            return null;
        }
        $slash = strrpos($this->class, '\\');

        return $slash === false ? $this->class : substr($this->class, $slash + 1);
    }

    /**
     * The function written the way the catalog writes it.
     */
    public function function(): string
    {
        if ($this->class !== null) {
            return $this->class . '::' . $this->member;
        }

        return ($this->namespace === '' ? '' : $this->namespace . '\\') . $this->member;
    }

    /**
     * The function written for a reader, without the namespace.
     */
    public function display(): string
    {
        if ($this->isMain()) {
            return 'top-level code';
        }

        return $this->class === null ? $this->member : $this->classShort() . '::' . $this->member;
    }

    /**
     * The namespace written for a reader.
     */
    public function namespaceLabel(): string
    {
        return $this->namespace === '' ? '(global namespace)' : $this->namespace;
    }
}
