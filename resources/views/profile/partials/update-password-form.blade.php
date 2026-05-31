<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form
        method="post"
        action="{{ route('password.update') }}"
        class="mt-6 space-y-6"
        x-data="passwordSecurityForm()"
        x-on:submit="submitIfValid($event)"
    >
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" x-model="password" />

            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Seguridad de la nueva password</p>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <template x-for="rule in rules" :key="rule.key">
                        <li class="flex items-center gap-2">
                            <span
                                class="inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold"
                                x-bind:class="rule.valid ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'"
                                x-text="rule.valid ? '\u2713' : '\u2022'"
                            ></span>
                            <span x-text="rule.label"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" x-model="passwordConfirmation" />

            <p
                class="mt-3 text-sm"
                x-bind:class="confirmationIsComplete && confirmationMatches ? 'text-emerald-600' : 'text-slate-500'"
                x-text="confirmationMessage"
            ></p>

            <p
                x-cloak
                x-show="attemptedSubmit && !canSubmit"
                class="mt-2 text-sm text-red-600"
            >
                Corrige la nueva password antes de guardar.
            </p>

            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button x-bind:disabled="!canSubmit" x-bind:class="!canSubmit ? 'cursor-not-allowed opacity-60' : ''">{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
