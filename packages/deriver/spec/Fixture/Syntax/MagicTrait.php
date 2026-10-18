<?php

trait Value { public function read() { return __TRAIT__; } } class Box { use Value; }
function subject() { return (new Box())->read(); }
