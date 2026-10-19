<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['Announcements', 'announcements', 'News and updates from the staff.', 'Announcements', 'public'],
            ['Discussions', 'discussions', 'General discussions about anything.', 'Discussions', 'public'],
            ['Bugs', 'bugs', 'Report problems you found.', 'Bugs', 'public'],
            ['Suggestions', 'suggestions', 'Share your ideas to improve the forum.', 'suggestions', 'public'],
            ['Map Editor', 'map-editor', 'Share and discuss your maps.', 'MapEditor', 'public'],
            ['Fan Art', 'fan-art', 'Drawings, videos and creations.', 'FanArt', 'public'],
            ['Tribes', 'tribes', 'Recruit members and present your tribe.', 'Tribes', 'public'],
            ['Off Topic', 'off-topic', 'Everything else.', 'OffTopic', 'public'],
            ['Staff Room', 'staff-room', 'Private section for the moderation team.', 'tribeforum', 'private'],
        ];
        $french = [
            ['Annonces', 'fr-annonces', 'Les nouvelles de l’équipe.', 'Announcements', 'public'],
            ['Discussions', 'fr-discussions', 'Discussions générales sur tous les sujets.', 'Discussions', 'public'],
            ['Bugs', 'fr-bugs', 'Signalez les problèmes rencontrés.', 'Bugs', 'public'],
            ['Suggestions', 'fr-suggestions', 'Vos idées pour améliorer le forum.', 'suggestions', 'public'],
            ['Tribus', 'fr-tribus', 'Recrutez et présentez votre tribu.', 'Tribes', 'public'],
            ['Hors-sujet', 'fr-hors-sujet', 'Tout le reste.', 'OffTopic', 'public'],
        ];

        $all = array_merge(
            array_map(fn ($s) => [...$s, 'en'], $sections),
            array_map(fn ($s) => [...$s, 'fr'], $french),
        );
        foreach ($all as $i => [$name, $slug, $description, $icon, $visibility, $community]) {
            Section::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'icon' => "img/sections/{$icon}.png",
                'community' => $community,
                'visibility' => $visibility,
                'position' => $i + 1,
            ]);
        }
    }
}
