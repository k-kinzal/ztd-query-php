<?php

function value() { return 7; }
function subject() { $call = value(...); return $call(); }
