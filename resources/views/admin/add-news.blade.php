<x-admin-layout>
<x-slot:title>Add News</x-slot:title>
<livewire:admin.add-news :id="request()->query('id') ? (int)request()->query('id') : null" />
</x-admin-layout>
