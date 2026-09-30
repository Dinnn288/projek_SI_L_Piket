<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserBaru;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = UserBaru::orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,siswa,guru,kepala_sekolah'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        UserBaru::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(UserBaru $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, UserBaru $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,siswa,guru,kepala_sekolah'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->role = $data['role'];
        $user->status = $data['status'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(UserBaru $user): RedirectResponse
    {
        if ((int) Auth::id() === (int) $user->id) {
            return back()->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        $hasRelation = $user->student()->exists()
            || $user->teacher()->exists()
            || $user->temporaryReporters()->exists()
            || $user->reportHistories()->exists()
            || $user->notifications()->exists();

        if ($hasRelation) {
            return back()->with('error', 'Akun tidak dapat dihapus karena masih digunakan oleh data terkait.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }
}
