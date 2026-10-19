<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    // Feeds only list public content, whoever asks
    public function latest()
    {
        $threads = Thread::with(['section', 'user', 'firstPost'])
            ->whereIn('section_id', Section::where('visibility', 'public')->select('id'))
            ->latest('id')->limit(30)->get();

        return $this->rss(config('app.name'), route('home'), __('Latest threads'), $threads);
    }

    public function section(Section $section)
    {
        abort_if($section->isPrivate(), 404);
        $threads = $section->threads()->with(['section', 'user', 'firstPost'])->latest('id')->limit(30)->get();

        return $this->rss(config('app.name').' · '.$section->name, route('sections.show', $section), (string) $section->description, $threads);
    }

    // Nickname suggestions for @mention autocomplete
    public function users(Request $request)
    {
        $q = (string) $request->query('q', '');
        if (mb_strlen($q) < 1 || mb_strlen($q) > 30) {
            return response()->json([]);
        }
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

        return response()->json(
            User::where('name', 'ilike', $like)->orderByRaw('length(name)')->limit(8)->get(['id', 'name'])
                ->map(fn ($u) => ['name' => $u->name, 'color' => $u->displayColor()])
        );
    }

    protected function rss(string $title, string $link, string $description, $threads)
    {
        return response()
            ->view('feeds.rss', compact('title', 'link', 'description', 'threads'))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
