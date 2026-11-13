<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserRole: string
{
    use HasOptions;

    case Admin = 'admin';
    case Teacher = 'teacher';
    case Parent = 'parent';
    case Student = 'student';

    /**
     * Get the human-readable label of the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Teacher => 'Teacher',
            self::Parent => 'Parent / Guardian',
            self::Student => 'Learner',
        };
    }

    /**
     * Get the palette key that represents the role.
     */
    public function color(): string
    {
        return match ($this) {
            self::Admin => 'grape',
            self::Teacher => 'leaf',
            self::Parent => 'carrot',
            self::Student => 'sky',
        };
    }

    /**
     * Get the icon name that represents the role.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Admin => 'shield-check',
            self::Teacher => 'graduation-cap',
            self::Parent => 'house-heart',
            self::Student => 'gamepad-2',
        };
    }

    /**
     * Get the name of the portal (or app) the role signs in to.
     */
    public function portalLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrator Portal',
            self::Teacher => 'Teacher Portal',
            self::Parent => 'Parent Portal',
            self::Student => 'NutriMind Game',
        };
    }

    /**
     * Get the name of the route the role lands on after signing in.
     */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Teacher => 'teacher.dashboard',
            self::Parent => 'parent.dashboard',
            self::Student => 'home',
        };
    }

    /**
     * Determine whether the role may sign in to the web portals.
     */
    public function canAccessWeb(): bool
    {
        return $this !== self::Student;
    }

    /**
     * Get the roles that have a web portal, in display order.
     *
     * @return list<self>
     */
    public static function webPortals(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role->canAccessWeb(),
        ));
    }
}
