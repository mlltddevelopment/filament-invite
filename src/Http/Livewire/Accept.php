<?php

namespace Concept7\FilamentInvite\Http\Livewire;

use App\Models\User;
use Concept7\FilamentInvite\Events\InviteProcessedEvent;
use Concept7\FilamentInvite\Models\Invite;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasRoutes;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\SimplePage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class Accept extends SimplePage
{
    use HasRoutes;
    use InteractsWithFormActions;
    use WithRateLimiting;

    public string $acceptId;

    public string $hash;

    public $submitted = false;

    public $expired = false;
    public $invite;

    /**
     * @var view-string
     */
    protected static string $view = 'filament-invite::accept';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function getTitle(): string
    {
        return __('Accept');
    }

    public function mount(string $acceptId = null, string $hash = null): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
        $this->acceptId = $acceptId ?? request()->query('acceptId');
        $this->hash = $hash ?? request()->query('hash');
        $this->expired = ! Invite::query()
            ->where('id', $this->acceptId)
            ->where('token', $this->hash)
            ->where('expires_at', '>=', now())
            ->exists();

        $this->invite = Invite::where('id', $this->acceptId)
            ->where('token', $this->hash)
            ->where('expires_at', '>=', now())
            ->first();

        $this->form->fill();
    }

    /**
     * @throws ValidationException
     */
    public function submit()
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::pages/auth/login.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]))
                ->body(array_key_exists('body', __('filament-panels::pages/auth/login.notifications.throttled') ?: []) ? __('filament-panels::pages/auth/login.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]) : null)
                ->danger()
                ->send();

            return null;
        }

        if ($this->expired) {
            throw ValidationException::withMessages([
                'data.email' => __('Link expired'),
            ]);
        }

        $data = $this->form->getState();

        $invite = Invite::query()
            ->where('id', $this->acceptId)
            ->where('token', $this->hash)
            ->where('expires_at', '>=', now())
            ->firstOrFail();

        $user = User::where('email', $invite->email)->first();

        $user->update([
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $invite->delete();

        event(new InviteProcessedEvent($user, $data['password']));

        $this->submitted = true;

        if (! Filament::auth()->attempt($data)) {
            throw ValidationException::withMessages([
                'data.password' => __('Login failed'),
            ]);

            // session()->regenerate();

            return route('filament.auth.login');
        }

        return redirect()->intended(route(config('filament-invite.after_login_redirect_route')));

    }

    public function form(Form $form): Form
    {
        return $form;
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        $email = $this->invite ? $this->invite->email : '';
        return TextInput::make('email')
            ->label(__('E-mail address'))
            ->autocomplete()
            ->autofocus()
            ->default($email)
            ->disabled()
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('Password'))
            ->password()
            ->autocomplete('current-password')
            ->required()
            ->rules([
                Password::defaults(),
            ])
            ->confirmed()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('password_confirmation')
            ->label(__('Password confirmation'))
            ->password()
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label(__('filament-panels::pages/auth/login.form.actions.authenticate.label'))
            ->submit('authenticate');
    }
}
