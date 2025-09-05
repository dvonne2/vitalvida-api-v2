<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\ActivityLog;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($roleQuery) use ($request) {
                $roleQuery->where('name', $request->role);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'status' => 'required|in:active,inactive'
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'name' => $request->name,
            'password' => bcrypt($request->password),
            'role' => $request->role,
            'status' => $request->status
        ]);

        // Log the activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'Created user: ' . $user->username,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => ['user_id' => $user->id],
            'timestamp' => now()
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully');
    }

    /**
     * Display the specified user
     */
    public function show(User $user)
    {
        $user->load('roles', 'activityLogs');
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'name' => 'required|string|max:255',
            'role' => 'required|exists:roles,name',
            'status' => 'required|in:active,inactive'
        ]);

        $user->update([
            'username' => $request->username,
            'email' => $request->email,
            'name' => $request->name,
            'role' => $request->role,
            'status' => $request->status
        ]);

        // Log the activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'Updated user: ' . $user->username,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => ['user_id' => $user->id],
            'timestamp' => now()
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account');
        }

        $username = $user->username;
        $user->delete();

        // Log the activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'Deleted user: ' . $username,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => ['deleted_user_id' => $user->id],
            'timestamp' => now()
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(User $user)
    {
        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        // Log the activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'Changed user status: ' . $user->username . ' to ' . $newStatus,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'details' => ['user_id' => $user->id, 'new_status' => $newStatus],
            'timestamp' => now()
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User status updated successfully');
    }
} 