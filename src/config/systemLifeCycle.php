<?php

return [

    /*
     * How many days to retain lifecycle execution logs.
     * Logs older than this will be deleted by the clean-up command.
     */
    'log_retention_days' => 90,

    /*
     * How many days to retain completed lifecycle model records.
     * Completed records are no longer active — the logs retain the audit trail.
     */
    'completed_model_retention_days' => 30,

    /*
     * Enable this if your models use custom morph map aliases instead of
     * fully-qualified class names for polymorphic relations.
     * When true, the mapping defined in 'relation_mapping' will be applied.
     */
    'custom_relation_mapping' => false,

    /*
     * Define your custom morph map here when 'custom_relation_mapping' is true.
     * Example: ['user' => \App\Models\User::class]
     */
    'relation_mapping' => [],

    /*
     * The column type used for model_id in polymorphic relations.
     * Must be set before running migrations.
     * Supported: 'string', 'integer', 'ulid', 'uuid'
     */
    'model_id_type' => 'string',

    /*
     * Maximum number of execution attempts before a lifecycle model is marked as failed.
     * Once attempts reach this threshold, the status is set to 'failed' instead of retrying.
     */
    'max_attempts' => 3,

    /*
     * Schedule configuration for the automatic commands.
     * Set 'enabled' to false to disable auto-scheduling entirely
     * and manage the commands in your own scheduler.
     *
     * Supported frequency values: any method name on Laravel's scheduling Event,
     * e.g. 'everyMinute', 'everyFiveMinutes', 'everyTenMinutes', 'hourly', 'daily', etc.
     */
    'schedule' => [
        'enabled' => true,

        'run' => [
            // How often the run command executes
            'frequency' => 'hourly',

            // Records with executes_at within this window (past and future) are eligible.
            // Should match or slightly exceed the frequency to avoid missing records.
            'window_in_minutes' => 60,

            // Records with executes_at older than this are considered stale
            // and will have their executes_at reset so they get picked up again.
            'stale_after_minutes' => 120,
        ],

        'logs_clean_up' => [
            'frequency' => 'weekly',
        ],

        'completed_models_clean_up' => [
            'frequency' => 'weekly',
        ],
    ],

];
