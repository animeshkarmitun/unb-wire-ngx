<x-admin-layout>
<x-slot:title>My Profile</x-slot:title>

<div class="max-w-[560px]">
    <h1 class="font-serif text-2xl font-bold mb-4">My Profile</h1>

    <div class="bg-panel border rounded-xl p-5 space-y-3">
        @include('profile.partials.update-profile-information-form')
    </div>

    <div class="bg-panel border rounded-xl p-5 space-y-3 mt-6">
        @include('profile.partials.update-password-form')
    </div>
</div>

<x-toast />
</x-admin-layout>
