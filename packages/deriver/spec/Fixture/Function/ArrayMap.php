<?php

function subject() { return array_map(fn ($value) => $value * 2, [1,2]); }
