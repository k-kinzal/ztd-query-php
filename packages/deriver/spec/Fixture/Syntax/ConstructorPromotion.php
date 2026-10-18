<?php

class Box { public function __construct(public readonly int $value) {} }
function subject() { return (new Box(7))->value; }
