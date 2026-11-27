<?php

use Illuminate\Support\Facades\Schedule;

// Runs only where the scheduler runs (`php artisan schedule:work` or a cron
// calling `schedule:run`); otherwise run the command by hand.
Schedule::command('example-images:prune')->daily();
