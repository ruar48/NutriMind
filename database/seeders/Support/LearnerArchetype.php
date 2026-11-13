<?php

namespace Database\Seeders\Support;

/**
 * The kinds of learners in the demo school, from consistently outstanding to needing support.
 */
enum LearnerArchetype: string
{
    case Star = 'star';
    case Strong = 'strong';
    case Steady = 'steady';
    case Developing = 'developing';
    case NeedsSupport = 'needs_support';

    /**
     * Get the share of the learners with this archetype.
     */
    public function share(): float
    {
        return match ($this) {
            self::Star => 0.12,
            self::Strong => 0.22,
            self::Steady => 0.30,
            self::Developing => 0.22,
            self::NeedsSupport => 0.14,
        };
    }

    /**
     * Get the range of the share of medium-difficulty questions the learner answers correctly.
     *
     * @return array{0: float, 1: float}
     */
    public function abilityRange(): array
    {
        return match ($this) {
            self::Star => [0.92, 0.97],
            self::Strong => [0.86, 0.92],
            self::Steady => [0.80, 0.87],
            self::Developing => [0.73, 0.81],
            self::NeedsSupport => [0.58, 0.71],
        };
    }

    /**
     * Get the range of the probability that the learner takes an assigned quiz or keeps up with game work.
     *
     * @return array{0: float, 1: float}
     */
    public function engagementRange(): array
    {
        return match ($this) {
            self::Star => [0.95, 0.99],
            self::Strong => [0.90, 0.97],
            self::Steady => [0.84, 0.93],
            self::Developing => [0.76, 0.88],
            self::NeedsSupport => [0.58, 0.76],
        };
    }

    /**
     * Get the range of the weekly improvement of the learner's ability.
     *
     * @return array{0: float, 1: float}
     */
    public function growthRange(): array
    {
        return match ($this) {
            self::Star => [0.000, 0.003],
            self::Strong => [0.002, 0.006],
            self::Steady => [0.003, 0.008],
            self::Developing => [0.006, 0.012],
            self::NeedsSupport => [0.007, 0.014],
        };
    }

    /**
     * Get the typical share of a past quarter's module activities the learner completed.
     */
    public function pastModuleCompletion(): float
    {
        return match ($this) {
            self::Star => 1.0,
            self::Strong => 0.97,
            self::Steady => 0.92,
            self::Developing => 0.82,
            self::NeedsSupport => 0.62,
        };
    }

    /**
     * Get how fast the learner works through the current quarter's activities (1 = on schedule).
     */
    public function pace(): float
    {
        return match ($this) {
            self::Star => 1.25,
            self::Strong => 1.12,
            self::Steady => 1.0,
            self::Developing => 0.85,
            self::NeedsSupport => 0.62,
        };
    }

    /**
     * Get the longest time, in hours, since an active learner's last game session.
     */
    public function hoursSinceLastSession(): int
    {
        return match ($this) {
            self::Star => 20,
            self::Strong => 36,
            self::Steady => 60,
            self::Developing => 96,
            self::NeedsSupport => 140,
        };
    }

    /**
     * Get how likely a learner of this archetype is to stop playing for a while.
     */
    public function inactivityWeight(): float
    {
        return match ($this) {
            self::Star => 0.3,
            self::Strong => 0.6,
            self::Steady => 1.2,
            self::Developing => 2.5,
            self::NeedsSupport => 3.5,
        };
    }
}
