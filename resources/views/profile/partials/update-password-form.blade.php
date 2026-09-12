<section>
    <h2 class="text-lg font-medium text-ink mb-1">Update Password</h2>
    <p class="text-sm text-muted-2 mb-4">Ensure your account is using a long, random password to stay secure.</p>

    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="text-xs font-semibold">Current Password</label>
            <input id="update_password_current_password" name="current_password" type="password"
                class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-crimson/30 focus:border-crimson"
                autocomplete="current-password" />
            @error('current_password', 'updatePassword')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="update_password_password" class="text-xs font-semibold">New Password</label>
            <input id="update_password_password" name="password" type="password"
                class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-crimson/30 focus:border-crimson"
                autocomplete="new-password" />
            @error('password', 'updatePassword')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="update_password_password_confirmation" class="text-xs font-semibold">Confirm Password</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-crimson/30 focus:border-crimson"
                autocomplete="new-password" />
        </div>

        <div class="flex items-center gap-3">
            <x-btn variant="primary" type="submit">Save</x-btn>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-green-600 font-medium">Saved.</p>
            @endif
        </div>
    </form>
</section>
