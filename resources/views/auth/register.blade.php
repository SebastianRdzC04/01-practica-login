<x-guest-layout>
    <form
        method="POST"
        action="{{ route('register') }}"
        x-data="passwordSecurityForm()"
        x-on:submit="submitIfValid($event)"
    >
        @csrf

        <div class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
            Este registro crea cuentas con el rol <strong>cliente</strong>.
        </div>

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            x-model="password"
                            required autocomplete="new-password" />

            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Seguridad de la password</p>
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

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation"
                            x-model="passwordConfirmation"
                            required autocomplete="new-password" />

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
                Corrige la password antes de enviar el formulario.
            </p>

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4" x-bind:disabled="!canSubmit" x-bind:class="!canSubmit ? 'cursor-not-allowed opacity-60' : ''">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
