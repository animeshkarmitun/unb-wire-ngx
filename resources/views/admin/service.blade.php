<x-admin-layout>
<x-slot:title>{{ $service==='en'?'English Service':'Bangla Service' }}</x-slot:title>
<livewire:admin.wire-service-view :service="$service" />
</x-admin-layout>
