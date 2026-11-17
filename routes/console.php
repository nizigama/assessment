<?php

use Illuminate\Support\Facades\Schedule;

// Fallback for Pixelforge webhooks that never arrive.
Schedule::command('generations:sync')->everyMinute()->withoutOverlapping();
