<?php

class Box { private function value() { return 7; } public function read() { return $this->value(); } }
function subject() { return (new Box())->read(); }
