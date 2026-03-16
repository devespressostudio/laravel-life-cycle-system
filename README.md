# Laravel Life Cycle System

A Laravel package for managing multi-stage, queue-driven lifecycle workflows on Eloquent models. Define workflows with ordered stages, attach them to any model, and let the system execute, retry, and log every transition automatically.

## Requirements

- PHP 8.1+
- Laravel 10 or 11

## Installation

```bash
composer require devespresso/system-life-cycle
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=devespresso-life-cycle-config
php artisan vendor:publish --tag=devespresso-life-cycle-migrations
php artisan migrate
```

## Configuration

```php
// config/systemLifeCycle.php

return [
    // Days to keep execution logs (cleaned up by the daily command)
    'log_retention_days' => 90,

    // Days to keep completed lifecycle model records
    'completed_model_retention_days' => 30,

    // Column type for model_id in polymorphic relations
    // Must be set BEFORE running migrations
    // Supported: 'string', 'integer', 'ulid', 'uuid'
    'model_id_type' => 'string',

    // Total number of execution attempts before a record is marked as failed
    'max_attempts' => 3,

    // Set to true and populate 'relation_mapping' to use custom morph aliases
    'custom_relation_mapping' => false,
    'relation_mapping' => [],
];
```

> **Important:** Set `model_id_type` before running migrations. It controls the column type used for `model_id` in the `system_life_cycle_models` and `system_life_cycle_logs` tables and cannot be changed after migration without a manual schema change.

## Core Concepts

### Lifecycle

A `SystemLifeCycle` is the workflow definition. It has a unique `code`, an active flag, date range, and an ordered set of stages.

### Stage

A `SystemLifeCycleStage` belongs to a lifecycle and holds the `sequence` (order) and the fully-qualified class name of the service that executes it.

### Lifecycle Model

A `SystemLifeCycleModel` is the per-model record that tracks where a specific Eloquent model is in a specific lifecycle — current stage, status, attempts, payload, and scheduling.

### Lifecycle Log

A `SystemLifeCycleLog` records every execution attempt (success or failure) with the stage, status, payload snapshot, and any error message.

## Statuses

| Status       | Meaning                                      |
|--------------|----------------------------------------------|
| `pending`    | Waiting to be picked up                      |
| `processing` | Claimed by the current run batch             |
| `completed`  | All stages finished successfully             |
| `failed`     | Exceeded max attempts                        |
| `success`    | Used in logs to mark a successful execution  |

## Usage

### 1. Enable lifecycles on a model

Add the `EnableSystemLifeCycles` trait to any Eloquent model:

```php
use Devespresso\SystemLifeCycle\Traits\EnableSystemLifeCycles;

class User extends Model
{
    use EnableSystemLifeCycles;
}
```

This provides the following methods:

```php
// Attach a lifecycle to the model
// Idempotent — returns the existing record if already enrolled,
// regardless of which stage the model is currently on
$user->addLifeCycleByCode('onboarding');

// Re-enroll from the beginning (resets stage, status, attempts, payload)
// Creates a fresh record if the model was never enrolled
$user->reEnrollLifeCycle('onboarding');

// Get the raw SystemLifeCycleModel record for a lifecycle
$record = $user->getLifeCycleByCode('onboarding');
// $record->status, $record->attempts, $record->payload ...

// Get the stage service instance for the model's current stage
$stage = $user->getLifeCycleStageByCode('onboarding'); // returns ?LifeCycleStageContract

// Manually advance to the next stage (bypasses logging and payload propagation)
$user->setNextLifeCycleStage('onboarding');

// Remove the lifecycle from the model
$user->removeLifeCycle('onboarding');

// Query all lifecycle model records for this model
$user->lifeCycles()->get();
```

### 2. Create a stage service

Extend `SystemLifeCycleService` for each stage in your workflow:

```php
use Devespresso\SystemLifeCycle\SystemLifeCycleService;

class SendWelcomeEmailStage extends SystemLifeCycleService
{
    public function handle(): void
    {
        // $this->model    — the Eloquent model being processed
        // $this->params   — the payload array (read/write between stages)
        // $this->systemLifeCycleModel — the raw lifecycle model record

        Mail::to($this->model->email)->send(new WelcomeEmail($this->model));

        $this->setParam('welcome_sent_at', now()->toDateTimeString());
    }

    public function shouldContinueToNextStage(): bool
    {
        // Return false to reschedule this stage for later
        return true;
    }
}
```

**Available helpers in your stage:**

| Helper | Description |
|--------|-------------|
| `$this->model` | The Eloquent model attached to this lifecycle record |
| `$this->params` | The payload array (persisted across stages) |
| `$this->systemLifeCycleModel` | The `SystemLifeCycleModel` record |
| `$this->setParam(key, value)` | Write a value into the payload |
| `$this->getParam(key)` | Read a value from the payload |
| `$this->isRetry()` | Returns `true` if this is a retry attempt (attempts >= 1) |
| `$this->setExecutesAt()` | Override to return a `Carbon` instance for deferred rescheduling |

### 3. Register the lifecycle

Use the interactive Artisan command to create a lifecycle and its stages:

```bash
php artisan devespresso:life-cycle:create
```

Or create them programmatically:

```php
use Devespresso\SystemLifeCycle\Models\SystemLifeCycle;
use Devespresso\SystemLifeCycle\Models\SystemLifeCycleStage;

$lifecycle = SystemLifeCycle::create([
    'name'             => 'User Onboarding',
    'code'             => 'onboarding',
    'active'           => true,
    'starts_at'        => now(),
    'activate_by_cron' => true,
]);

SystemLifeCycleStage::create([
    'system_life_cycle_id' => $lifecycle->id,
    'sequence'             => 1,
    'name'                 => 'Send Welcome Email',
    'class'                => SendWelcomeEmailStage::class,
]);

SystemLifeCycleStage::create([
    'system_life_cycle_id' => $lifecycle->id,
    'sequence'             => 2,
    'name'                 => 'Assign Default Role',
    'class'                => AssignDefaultRoleStage::class,
]);
```

### 4. Attach models to the lifecycle

```php
// When a user registers
$user->addLifeCycleByCode('onboarding');
```

### 5. Schedule the run command

Add the run command to your console schedule to process pending lifecycle records:

```php
// routes/console.php or app/Console/Kernel.php

$schedule->command('devespresso:life-cycle:run')->everyMinute();
```

The command:
1. Resets stale `executes_at` values (records stuck > 20 minutes)
2. Assigns the first stage to any records missing one
3. Claims all `pending` records as `processing` using a batch ID
4. Dispatches a `SystemLifeCycleExecuteJob` for each claimed record

### 6. Schedule clean-up commands

```php
$schedule->command('devespresso:life-cycle:logs-clean-up')->daily();
$schedule->command('devespresso:life-cycle:completed-models-clean-up')->daily();
```

## Execution Flow

```
devespresso:life-cycle:run
    └── SystemLifeCycleExecuteJob (queued)
            └── YourStageService::execute()
                    ├── shouldContinueToNextStage() == false
                    │       └── reschedule: status=pending, executes_at=setExecutesAt()
                    └── shouldContinueToNextStage() == true (or isRetry())
                            ├── handle()
                            ├── createSuccessLog()
                            └── setNextStage()
                                    ├── has next stage → pending + new stage_id + reset attempts
                                    └── no next stage  → completed
```

**On exception:**
```
execute() catches Exception
    ├── createErrorLog()
    └── manageFailedCycle()
            ├── attempts + 1 < max_attempts  → pending, executes_at +1hr, attempts++
            └── attempts + 1 >= max_attempts → failed, attempts++
```

> With `max_attempts = 3`, a record gets exactly 3 total execution attempts before being marked as `failed`.

## Stage Payload

The `payload` column is a JSON object shared across all stages of a lifecycle run. Use `setParam` and `getParam` to pass data between stages without extra queries:

```php
// Stage 1
$this->setParam('subscription_id', $subscription->id);

// Stage 2
$subscriptionId = $this->getParam('subscription_id');
```

## Deferred Execution

Return a specific time from `setExecutesAt()` to control when a rescheduled stage runs:

```php
public function shouldContinueToNextStage(): bool
{
    return $this->model->payment_verified_at !== null;
}

public function setExecutesAt(): ?Carbon
{
    // Check again in 30 minutes
    return now()->addMinutes(30);
}
```

## Re-enrollment

To restart a completed (or failed) lifecycle from the beginning:

```php
$user->reEnrollLifeCycle('onboarding');
```

This resets the record to stage 1 with `status=pending`, `attempts=0`, and clears payload, batch, and `executes_at`. If the model was never enrolled it creates a fresh record, making it safe to call unconditionally.

## Retry Behaviour

When `shouldContinueToNextStage()` returns `false` on the first attempt, the stage is rescheduled silently (no log). On subsequent attempts (`isRetry() === true`) the stage runs regardless, so a model is never permanently stuck waiting.

## Custom Model ID Types

If your models use ULIDs, UUIDs, or integer IDs, configure the type before running migrations:

```php
// config/systemLifeCycle.php
'model_id_type' => 'ulid',  // 'string' | 'integer' | 'ulid' | 'uuid'
```

## Custom Morph Map

If your application uses morph aliases, enable custom mapping in the config:

```php
'custom_relation_mapping' => true,
'relation_mapping' => [
    'user'  => \App\Models\User::class,
    'order' => \App\Models\Order::class,
],
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `devespresso:life-cycle:create` | Interactively create a lifecycle with stages |
| `devespresso:life-cycle:run` | Process and dispatch all pending lifecycle records |
| `devespresso:life-cycle:logs-clean-up` | Delete logs older than `log_retention_days` |
| `devespresso:life-cycle:completed-models-clean-up` | Delete completed records older than `completed_model_retention_days` |

## Database Schema

| Table | Description |
|-------|-------------|
| `system_life_cycles` | Lifecycle definitions |
| `system_life_cycle_stages` | Ordered stages belonging to a lifecycle |
| `system_life_cycle_models` | Per-model tracking of current position in a lifecycle |
| `system_life_cycle_logs` | Immutable execution history (success and failure) |

All tables use a `bigIncrements` internal primary key (`internal_id`) and a public ULID identifier (`id`) for foreign key relationships.

## Testing

```bash
composer test
```

The package uses [Orchestra Testbench](https://github.com/orchestral/testbench) with an SQLite in-memory database.

## License

MIT
