<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ReportPolicy
{
    public function resolve(User $user, Report $report): bool
    {
        return Gate::forUser($user)->allows('moderate', $report->post->thread->section);
    }
}
