<?php

use App\Enums\PerformanceBand;
use App\Enums\ProgressStatus;
use App\Enums\QuizStatus;

it('renders an enum with its label, palette color and icon', function () {
    $view = $this->blade('<x-status-badge :status="$status" />', ['status' => QuizStatus::Published]);

    $view->assertSeeText('Published')
        ->assertSee('data-color="leaf"', false)
        ->assertSee('data-icon="circle-check"', false);
});

it('maps each case to the color its enum declares', function (ProgressStatus $status, string $color) {
    $this->blade('<x-status-badge :status="$status" />', ['status' => $status])
        ->assertSee('data-color="'.$color.'"', false)
        ->assertSeeText($status->label());
})->with([
    'not started' => [ProgressStatus::NotStarted, 'slate'],
    'in progress' => [ProgressStatus::InProgress, 'sky'],
    'completed' => [ProgressStatus::Completed, 'leaf'],
]);

it('renders a dot when the status has no icon', function () {
    $status = new class
    {
        public function label(): string
        {
            return 'Awaiting review';
        }

        public function color(): string
        {
            return 'sun';
        }
    };

    $view = $this->blade('<x-status-badge :status="$status" />', ['status' => $status]);

    $view->assertSeeText('Awaiting review')
        ->assertSee('size-1.5 rounded-full bg-sun-500', false)
        ->assertDontSee('data-icon=', false);
});

it('renders nothing without a status', function () {
    $view = $this->blade('<x-status-badge :status="null" />');

    expect(trim((string) $view))->toBe('');
});

it('uses the band palette for performance bands', function () {
    $this->blade('<x-status-badge :status="$band" />', ['band' => PerformanceBand::VerySatisfactory])
        ->assertSee('data-color="teal"', false)
        ->assertSeeText('Very Satisfactory');
});
