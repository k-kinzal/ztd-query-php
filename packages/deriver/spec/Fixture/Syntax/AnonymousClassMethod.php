<?php

function subject() { return (new class { public function read() { return 7; } })->read(); }
