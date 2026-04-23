<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:fetch-email')]
#[Description('Command description')]
class FetchEmail extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
