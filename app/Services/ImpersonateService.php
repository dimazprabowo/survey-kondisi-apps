<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonateService
{
    private const SESSION_KEY = 'impersonate_original_id';

    public function start(User $target): void
    {
        if (! Auth::check() || $this->isImpersonating() || Auth::id() === $target->id) {
            abort(403, 'Tidak dapat memulai impersonate.');
        }

        Session::put(self::SESSION_KEY, Auth::id());
        Auth::loginUsingId($target->id);
    }

    public function stop(): void
    {
        $originalId = Session::get(self::SESSION_KEY);

        if ($originalId) {
            Auth::loginUsingId($originalId);
            Session::forget(self::SESSION_KEY);
        }
    }

    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public function getOriginalUser(): ?User
    {
        $id = Session::get(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }
}
