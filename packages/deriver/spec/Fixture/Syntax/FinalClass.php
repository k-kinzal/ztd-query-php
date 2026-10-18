<?php

final class Box { public function value() { return 7; } }
function subject() { return (new Box())->value(); }
