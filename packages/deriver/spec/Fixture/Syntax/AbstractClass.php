<?php

abstract class Base { abstract public function value(): int; } class Box extends Base { public function value(): int { return 7; } }
function subject() { return (new Box())->value(); }
