<?php

namespace App\Livewire\Admin;

use App\Models\ModerationLog;
use App\Support\ForumSettings;
use Livewire\Component;

class ForumSettingsPage extends Component
{
    public bool $registrationOpen = true;

    public bool $captcha = true;

    public int $postsPerMinute = 5;

    public int $messagesPerMinute = 10;

    public int $maxPostLength = 20000;

    public int $maxGuilds = 5;

    // Empty means authors can always edit their posts
    public string $editWindow = '';

    public function mount(): void
    {
        $this->authorize('admin');
        $s = ForumSettings::all();
        $this->registrationOpen = (bool) $s['registration_open'];
        $this->captcha = (bool) $s['captcha'];
        $this->postsPerMinute = (int) $s['posts_per_minute'];
        $this->messagesPerMinute = (int) $s['messages_per_minute'];
        $this->maxPostLength = (int) $s['max_post_length'];
        $this->maxGuilds = (int) $s['max_guilds_per_user'];
        $this->editWindow = $s['edit_window_minutes'] ? (string) $s['edit_window_minutes'] : '';
    }

    public function save(): void
    {
        $this->authorize('admin');
        $this->validate([
            'postsPerMinute' => ['required', 'integer', 'min:1', 'max:120'],
            'messagesPerMinute' => ['required', 'integer', 'min:1', 'max:120'],
            'maxPostLength' => ['required', 'integer', 'min:100', 'max:100000'],
            'maxGuilds' => ['required', 'integer', 'min:1', 'max:50'],
            'editWindow' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);
        $before = ForumSettings::all();
        ForumSettings::save([
            'registration_open' => $this->registrationOpen,
            'captcha' => $this->captcha,
            'posts_per_minute' => $this->postsPerMinute,
            'messages_per_minute' => $this->messagesPerMinute,
            'max_post_length' => $this->maxPostLength,
            'max_guilds_per_user' => $this->maxGuilds,
            'edit_window_minutes' => $this->editWindow === '' ? null : (int) $this->editWindow,
        ]);
        $changed = array_diff_assoc(array_map('json_encode', ForumSettings::all()), array_map('json_encode', $before));
        if ($changed) {
            ModerationLog::record(auth()->user(), 'settings_update', auth()->user(), null, array_map('json_decode', $changed));
        }
        $this->dispatch('toast', message: __('Settings saved.'));
    }

    public function render()
    {
        return view('livewire.admin.forum-settings');
    }
}
