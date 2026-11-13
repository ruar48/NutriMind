<?php

namespace App\Services;

use App\Models\User;

/**
 * The result of StudentAccounts::create(): the new learner and the credentials to hand out once.
 */
final readonly class CreatedStudentAccount
{
    public function __construct(
        /** The new learner account, with the studentProfile relation loaded. */
        public User $user,
        /** The password in plain text; show it once and never store it. */
        public string $plainPassword,
        /** Whether the learner was enrolled in the given classroom. */
        public bool $enrolled,
    ) {}
}
