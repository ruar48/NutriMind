<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ForumReportReason: string
{
    use HasOptions;

    case Spam = 'spam';
    case Inappropriate = 'inappropriate';
    case Harassment = 'harassment';
    case OffTopic = 'off_topic';
    case Misinformation = 'misinformation';
    case Other = 'other';

    /**
     * Get the human-readable label of the reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam or advertising',
            self::Inappropriate => 'Inappropriate content',
            self::Harassment => 'Harassment or bullying',
            self::OffTopic => 'Off-topic',
            self::Misinformation => 'Misinformation',
            self::Other => 'Other',
        };
    }
}
