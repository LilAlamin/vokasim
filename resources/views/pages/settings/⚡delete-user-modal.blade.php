<?php

use App\Concerns\PasswordValidationRules;
use App\Actions\RevokeUserSessions;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(RevokeUserSessions $sessions): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        DB::transaction(function () use ($sessions): void {
            User::query()->where('role', UserRole::Admin)->where('is_active', true)->orderBy('id')->lockForUpdate()->get(['id']);
            $user = User::query()->lockForUpdate()->findOrFail(Auth::id());
            $user->delete();
            $sessions->deleteStoredSessions($user);
            Password::broker(config('fortify.passwords'))->deleteToken($user);
        });

        Auth::guard('web')->logoutCurrentDevice();
        Session::invalidate();
        Session::regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

            <flux:subheading>
                {{ __('Only accounts without academic history or assignments can be deleted. Contact an administrator to deactivate an account with records. Enter your password to confirm permanent deletion.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
