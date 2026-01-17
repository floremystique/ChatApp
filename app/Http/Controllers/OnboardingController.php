<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tag;
use App\Models\Profile;

class OnboardingController extends Controller
{
    
    public function create()
    {
        $user = auth()->user();

        // Always return a Profile instance (even if not saved yet)
        $profile = Profile::with('tags')->firstOrNew([
            'user_id' => $user->id,
        ]);

        // Ensure tags is a Collection even for new profiles
        $profile->setRelation('tags', $profile->tags ?? collect());

        $tags = Tag::orderBy('name')->get();

        return view('profile.onboarding', compact('profile', 'tags'));
    }


    // POST /onboarding
    public function store(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|string|max:50',
            'dob' => 'nullable|date',
            'bio' => 'nullable|string|max:120',
            'tags' => 'array',
            'tags.*' => 'integer',
        ]);

        $profile = Profile::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'looks_matter' => $request->has('looks_matter'),
                'mode' => $data['mode'],
                'dob' => $data['dob'] ?? null,
                'bio' => $data['bio'] ?? null,
            ]
        );

        $profile->tags()->sync($data['tags'] ?? []);

        return redirect()->route('match');
    }
}
