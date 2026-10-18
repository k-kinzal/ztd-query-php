<?php

function value($a, $b) { return $b; }
function subject() { return value(...[3, 7]); }
