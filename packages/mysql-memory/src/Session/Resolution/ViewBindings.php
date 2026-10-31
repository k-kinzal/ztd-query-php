<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use MySqlMemory\Dictionary\Binding\ViewBinding;
use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Evaluation\Compile\Settings;
use SqlSemantics\Statement\Declaration\Table;

/**
 * Resolves persistent view dependencies before a declaration snapshot is frozen.
 * The mutable work data belongs to this traversal, never to the shared dictionary.
 *
 * @visibility MySqlMemory\Session\Resolution
 */
final class ViewBindings
{
    /**
     * @var array<int, ViewBinding> Completed bindings, by stored definition identity
     */
    public array $views = [];

    /**
     * @var array<int, true> Definitions on the current dependency path
     */
    private array $visiting = [];

    /**
     * @var list<Table> Persistent declarations with resolved view outputs
     */
    public array $tables;

    /**
     * Starts a dependency traversal over the permanent catalog.
     */
    public function __construct(public readonly Dictionary $dictionary, public readonly Settings $settings)
    {
        $this->tables = $dictionary->declarations();
    }

    /**
     * Resolves dependencies first, retaining invalidity without altering any stored view.
     */
    public function resolve(View $view): ViewBinding
    {
        $id = spl_object_id($view);
        if (isset($this->views[$id])) {
            return $this->views[$id];
        }
        if (isset($this->visiting[$id])) {
            return new ViewBinding($view, $view->created, $view->declaration, [], false);
        }
        $this->visiting[$id] = true;
        $valid = true;
        foreach ($view->tables as $table) {
            $schema = $table->name->schema->value ?? $view->database;
            $dependency = $this->dictionary->schema($schema)->views[$table->name->name->value] ?? null;
            if ($dependency !== null && !$this->resolve($dependency)->valid) {
                $valid = false;
            }
        }
        $binding = Views::bind($view, $this->tables, $this->settings, $valid);
        foreach ($this->tables as $position => $table) {
            if ($table === $view->declaration) {
                $this->tables[$position] = $binding->declaration;
            }
        }
        unset($this->visiting[$id]);
        return $this->views[$id] = $binding;
    }
}
