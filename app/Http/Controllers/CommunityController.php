<?php

namespace App\Http\Controllers;

use App\Support\Community;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['community' => ['required', 'in:'.implode(',', array_keys(Community::all()))]]);
        Community::set($data['community']);

        return redirect()->route('home')->with('toast', __('Community: :name', ['name' => Community::label($data['community'])]));
    }
}
