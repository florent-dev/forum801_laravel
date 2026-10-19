<x-layouts.app :title="__('Report #:id', ['id' => $report->id])" bodyClass="has-thread">
    <x-slot:left><livewire:report-list :active-report-id="$report->id" /></x-slot:left>
    <livewire:report-panel :report="$report" />
    <livewire:thread-view :thread="$thread" :highlight-post-id="$post->id" highlight-style="reported" />
</x-layouts.app>
