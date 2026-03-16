<?php

namespace Devespresso\SystemLifeCycle\Commands;

use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSystemLifeCycleCommand extends Command
{
    protected $signature = 'devespresso:life-cycle:create';

    protected $description = 'Interactively create a new life cycle with its stages via the command line';

    public function handle(): int
    {
        DB::transaction(function () {
            // Collect lifecycle metadata from the user
            $lifeCycle = [
                'name'             => $this->ask('How do you want to name it?'),
                'code'             => $this->askCode(),
                'starts_at'        => $this->askDate('When do you want it to start?'),
                'activate_by_cron' => $this->confirm('Does it activate by the cron job?', true),
                'active'           => true,
            ];

            if ($this->confirm('Does it have an end date?', false)) {
                $lifeCycle['ends_at'] = $this->askDate('What is the end date?');
            }

            // Collect stage count before any DB writes so we don't create
            // a partial lifecycle if the user enters an invalid stage count
            $stageCount = $this->askStageCount();

            $lifeCycleId = SystemLifeCycle::create($lifeCycle)->id;

            // Create each stage in sequence order
            foreach (range(1, $stageCount) as $sequence) {
                SystemLifeCycleStage::create([
                    'sequence'             => $sequence,
                    'name'                 => $this->ask("Stage {$sequence}: What is the name?"),
                    'system_life_cycle_id' => $lifeCycleId,
                    'class'                => $this->askClass($sequence),
                ]);
            }
        });

        $this->info('Life cycle created successfully.');

        return 0;
    }

    /**
     * Ask for a unique lifecycle code, validating format before hitting the DB.
     * Falls back to an auto-generated UUID if the user leaves it empty.
     */
    private function askCode(): string
    {
        $code = $this->ask('Specify a code? (leave empty to auto-generate)');

        if (!$code) {
            return Str::uuid()->toString();
        }

        if (Str::contains($code, ' ')) {
            $this->error('Code cannot contain spaces.');
            return $this->askCode();
        }

        if (Str::length($code) > 50) {
            $this->error('Code cannot be more than 50 characters.');
            return $this->askCode();
        }

        if (SystemLifeCycle::where('code', $code)->exists()) {
            $this->error('Code is already in use.');
            return $this->askCode();
        }

        return $code;
    }

    /**
     * Ask for a date string and parse it with Carbon.
     * Re-prompts if the input cannot be parsed into a valid datetime.
     */
    private function askDate(string $question): string
    {
        $input = $this->ask($question);

        try {
            return Carbon::parse($input)->toDateTimeString();
        } catch (Exception $e) {
            $this->error('Invalid date, please try again.');
            return $this->askDate($question);
        }
    }

    /**
     * Ask for the fully-qualified handler class for a stage.
     * Re-prompts if the class cannot be found by the autoloader.
     */
    private function askClass(int $sequence): string
    {
        $class = $this->ask("Stage {$sequence}: What is the handler class? e.g App\\Services\\UserJoinLifeCycle");

        if (class_exists($class)) {
            return $class;
        }

        $this->error('Class not found.');

        return $this->askClass($sequence);
    }

    /**
     * Ask for the number of stages, ensuring at least one is defined.
     */
    private function askStageCount(): int
    {
        $count = (int) $this->ask('How many stages do you want?');

        if ($count < 1) {
            $this->error('You need at least one stage.');
            return $this->askStageCount();
        }

        return $count;
    }
}
