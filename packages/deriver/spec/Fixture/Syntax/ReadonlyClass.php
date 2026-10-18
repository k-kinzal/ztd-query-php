<?php

readonly class Box { public function __construct(public int $value) {} }
function subject() { return (new Box(7))->value; }
