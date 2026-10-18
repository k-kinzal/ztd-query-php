<?php

interface Value { public function read(): int; } class Box implements Value { public function read(): int { return 7; } }
function subject() { return (new Box())->read(); }
