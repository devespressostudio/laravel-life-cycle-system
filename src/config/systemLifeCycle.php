<?php

return [

    /*
     * How many days to retain lifecycle execution logs.
     * Logs older than this will be deleted by the daily clean-up command.
     */
    'log_retention_days' => 90,

    /*
     * How many days to retain completed lifecycle model records.
     * Records older than this will be deleted by the daily clean-up command.
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

];
