<x-layouts.app :title="$thread->title" bodyClass="has-thread">
    <x-slot:left><livewire:thread-list :section="$thread->section" :active-thread-id="$thread->id" /></x-slot:left>
    <livewire:thread-view :thread="$thread" :highlight-post-id="request()->integer('p') ?: null" highlight-style="targeted" />
</x-layouts.app>
