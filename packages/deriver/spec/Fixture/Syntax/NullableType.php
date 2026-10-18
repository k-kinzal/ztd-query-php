<?php

function value(?int $value): ?int { return $value; }
function subject() { return value(null); }
