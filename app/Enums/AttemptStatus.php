<?php

namespace App\Enums;

enum AttemptStatus: string
{
    /** Quiz downloaded to the device; answers not yet received. */
    case Started = 'started';
    /** Answers received and graded by the server. */
    case Submitted = 'submitted';
}
