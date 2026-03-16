<?php

namespace Devespresso\SystemLifeCycle\Enums;

enum LifeCycleStatus: string
{
    // Life cycle model statuses
    case Pending    = 'pending';
    case Processing = 'processing';
    case Completed  = 'completed';

    // Shared
    case Failed  = 'failed';

    // Log statuses
    case Success = 'success';
}
