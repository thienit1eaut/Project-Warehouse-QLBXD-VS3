<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',         // giữ tạm cho đến khi migration 008 drop cột này
        'role_id',
        'is_active',
        'is_protected',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'is_protected'      => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // -------------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------------

    /**
     * Kiểm tra User có Permission cho module.action hay không.
     *
     * Format key: 'module-slug.action'
     * Ví dụ: 'media.view', 'media.force-delete', 'media-folder.update'
     *
     * Dùng eager-load per-request (load 1 lần, cache trong relationship):
     * nếu $this->role đã được load (eager hoặc lazy), permissions cũng được
     * load 1 lần và PHP giữ trong memory — không N+1 khi gọi nhiều lần
     * trong cùng 1 request.
     *
     * is_protected không bypass Permission — không có logic đặc biệt ở đây.
     */
    public function hasPermission(string $permission): bool
    {
        // $permission format: 'module-slug.action'
        [$moduleSlug, $action] = explode('.', $permission, 2);

        return $this->role
            ?->permissions
            ->contains(function ($perm) use ($moduleSlug, $action) {
                return $perm->module->slug === $moduleSlug
                    && $perm->action === $action;
            }) ?? false;
    }

    // -------------------------------------------------------------------------
    // Legacy helpers — giữ lại cho đến khi middleware/code cũ được migrate
    // Xoá sau khi migration 008 (drop users.role) chạy và code cũ đã refactor
    // -------------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role?->slug === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role?->slug, ['admin', 'manager'], true);
    }
}