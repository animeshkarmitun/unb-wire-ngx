<x-admin-layout>
<x-slot:title>{{ $service==='en'?'English Service':'Bangla Service' }}</x-slot:title>
<livewire:admin.service-config :service="$service" />
</x-admin-layout>
