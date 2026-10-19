{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ $link }}</link>
        <description>{{ $description }}</description>
        <atom:link href="{{ url()->current() }}" rel="self" type="application/rss+xml" />
        @foreach ($threads as $thread)
            <item>
                <title>{{ $thread->title }}</title>
                <link>{{ $thread->url() }}</link>
                <guid isPermaLink="true">{{ $thread->url() }}</guid>
                <pubDate>{{ $thread->created_at->toRssString() }}</pubDate>
                <category>{{ $thread->section->name }}</category>
                @if ($thread->user)<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $thread->user->name }}</dc:creator>@endif
                <description>{{ $thread->firstPost && ! $thread->firstPost->moderated_at ? $thread->firstPost->excerpt(400) : '' }}</description>
            </item>
        @endforeach
    </channel>
</rss>
