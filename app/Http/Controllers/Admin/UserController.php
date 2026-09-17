<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('role') // load relationship để lấy slug
            ->when(
                $request->search,
                fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                             ->orWhere('email', 'like', "%{$request->search}%")
            )
            // Filter theo slug qua relationship — không query cột role ENUM cũ
            ->when(
                $request->role,
                fn ($q) => $q->whereHas('role', fn ($r) => $r->where('slug', $request->role))
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Transform: thêm 'role' slug vào từng user để frontend dùng roleBadge[u.role]
        // giữ nguyên contract JSON hiện tại, không đổi frontend
        $users->through(fn ($user) => array_merge($user->toArray(), [
            'role' => $user->role?->slug,
        ]));

        return Inertia::render('Admin/Users/Index', [
            'pageTitle' => 'Quản lý người dùng',
            'users'     => $users,
            'filters'   => $request->only(['search', 'role']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'pageTitle' => 'Thêm người dùng',
            'user'      => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', Password::min(8)->mixedCase()->numbers()],
            // Validate slug tồn tại trong bảng roles — không hard-code ID
            'role'      => ['required', Rule::exists('roles', 'slug')],
            'is_active' => ['boolean'],
        ], [
            'email.unique' => 'Email này đã được sử dụng.',
            'role.exists'  => 'Vai trò không hợp lệ.',
        ]);

        // Resolve role_id từ slug — không hard-code
        $roleId = Role::where('slug', $request->role)->value('id');

        User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role_id'   => $roleId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Đã tạo người dùng thành công.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'pageTitle' => 'Sửa người dùng: ' . $user->name,
            // Trả về slug string để Form.vue khởi tạo form.role đúng
            'user'      => array_merge($user->only('id', 'name', 'email', 'is_active'), [
                'role' => $user->role?->slug,
            ]),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['nullable', Password::min(8)->mixedCase()->numbers()],
            'role'      => ['required', Rule::exists('roles', 'slug')],
            'is_active' => ['boolean'],
        ], [
            'role.exists' => 'Vai trò không hợp lệ.',
        ]);

        $roleId = Role::where('slug', $request->role)->value('id');

        $data = [
            'name'      => $request->name,
            'email'     => $request->email,
            'role_id'   => $roleId,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'Đã cập nhật người dùng.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'Không thể tự xoá tài khoản của mình.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Đã xoá người dùng.');
    }
}