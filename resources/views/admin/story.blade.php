<x-admin-layout>
<x-slot:title>{{ $story->headline ?? 'Story' }}</x-slot:title>
<livewire:admin.story-view :publicId="$publicId" />
</x-admin-layout>
