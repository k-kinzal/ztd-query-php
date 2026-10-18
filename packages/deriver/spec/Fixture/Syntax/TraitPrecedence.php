<?php

trait A { public function read() { return 7; } } trait B { public function read() { return 9; } } class Box { use A, B { A::read insteadof B; } }
function subject() { return (new Box())->read(); }
