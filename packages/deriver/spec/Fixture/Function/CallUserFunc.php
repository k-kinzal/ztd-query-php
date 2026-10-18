<?php

function subject() { return call_user_func(fn ($value) => $value + 1, 6); }
