<x-admin-layout>
<x-slot:title>{{ $language==='bn' ? 'Bangla News' : 'English News' }}</x-slot:title>
<livewire:admin.news-list :language="$language" />
</x-admin-layout>
