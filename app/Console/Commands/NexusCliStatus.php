<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nexus:status')]
#[Description('Check the status of the Nexus CLI interaction.')]
class NexusCliStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = \Laravel\Prompts\text(
            label: 'What is your name?',
            placeholder: 'E.g. Taylor Otwell',
            required: true
        );

        $mood = \Laravel\Prompts\select(
            label: 'How are you feeling today?',
            options: [
                'happy' => 'Happy',
                'productive' => 'Productive',
                'tired' => 'Tired',
            ],
            default: 'productive'
        );

        \Laravel\Prompts\info("Hello, {$name}! It's great that you are feeling {$mood}.");

        return self::SUCCESS;
    }
}
