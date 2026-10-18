<?php

class Base { protected function value() { return 7; } } class Box extends Base { public function read() { return $this->value(); } }
function subject() { return (new Box())->read(); }
