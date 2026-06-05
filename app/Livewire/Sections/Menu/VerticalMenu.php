<?php

namespace App\Livewire\Sections\Menu;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class VerticalMenu extends Component
{
    public $role = null;

    public function mount()
    {
        $user = User::find(Auth::id());

        if (! $user) {
            $this->role = null;

            return;
        }

        if ($user->hasRole('Admin')) {
            $this->role = 'Admin';

            return;
        }

        $this->role = $user->getRoleNames()->first();
    }

    public function render()
    {
        return view('livewire.sections.menu.vertical-menu');
    }
}
