<section>
    <h2 class="text-lg font-medium text-ink mb-1">Profile Information</h2>
    <p class="text-sm text-muted-2 mb-4">Update your account's profile information and email address.</p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="text-xs font-semibold">Name</label>
            <input id="name" name="name" type="text"
                class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-crimson/30 focus:border-crimson"
                value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
            @error('name')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="text-xs font-semibold">Email</label>
            <input id="email" name="email" type="email"
                class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:outline-none focus:ring-2 focus:ring-crimson/30 focus:border-crimson"
                value="{{ old('email', $user->email) }}" required autocomplete="username" />
            @error('email')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="text-sm text-muted-2">
                        Your email address is unverified.

                        <button form="send-verification" class="underline text-sm text-crimson hover:text-crimson-dark focus:outline-none">
                            Click here to re-send the verification email.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-1 font-medium text-sm text-green-600">
                            A new verification link has been sent to your email address.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <x-btn variant="primary" type="submit">Save</x-btn>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-green-600 font-medium">Saved.</p>
            @endif
        </div>
    </form>
</section>
