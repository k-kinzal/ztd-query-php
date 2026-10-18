<?php

class Box { public function __toString(): string { return "seven"; } }
function subject() { return (string) new Box(); }
