<?php

use App\Models\Application;
use App\Models\Message;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

it('seeds every task with a curated title and description', function () {
    $this->seed(DatabaseSeeder::class);

    $briefs = DatabaseSeeder::taskBriefs();

    $tasks = Task::query()->get(['title', 'description']);

    expect($tasks)->not->toBeEmpty();

    foreach ($tasks as $task) {
        expect($briefs)->toContain([
            'title' => $task->title,
            'description' => $task->description,
        ]);
    }
});

it('seeds uploader countries from a varied set', function () {
    $this->seed(DatabaseSeeder::class);

    $countries = Task::query()
        ->with('uploader:id,country')
        ->get()
        ->pluck('uploader.country')
        ->filter()
        ->unique();

    expect($countries->count())->toBeGreaterThanOrEqual(5)
        ->and($countries)->toContain('BD', 'US', 'GB', 'IN', 'JP');
});

it('seeds human readable chat, application, and review text', function () {
    $this->seed(DatabaseSeeder::class);

    $expectedChat = collect(DatabaseSeeder::taskBriefs())
        ->flatMap(fn (array $brief) => collect(DatabaseSeeder::chatScripts())
            ->flatten()
            ->map(fn (string $message) => str_replace('{task}', $brief['title'], $message)))
        ->all();

    foreach (Message::query()->pluck('body') as $body) {
        expect($expectedChat)->toContain($body);
    }

    expect(Message::query()->whereNotNull('reply_to_id')->exists())->toBeTrue();

    $applicationMessages = DatabaseSeeder::applicationMessages();

    foreach (Application::query()->whereNotNull('message')->pluck('message') as $message) {
        expect($applicationMessages)->toContain($message);
    }

    $reviewComments = array_merge(DatabaseSeeder::reviewComments(), [
        'Clear brief and fast feedback.',
        'Great work on the handoff.',
        'Reliable collaborator.',
        'Good communication.',
    ]);

    foreach (Review::query()->whereNotNull('comment')->pluck('comment') as $comment) {
        expect($reviewComments)->toContain($comment);
    }
});

it('uses real user names in seeded notification text', function () {
    $this->seed(DatabaseSeeder::class);

    $payloads = DB::table('notifications')
        ->pluck('data')
        ->map(fn (string $data) => json_decode($data, true));

    expect($payloads)->not->toBeEmpty();

    $applicationNotification = $payloads->firstWhere('type', 'application_received');
    $applicantName = User::query()->whereKey($applicationNotification['user_id'])->value('name');

    $reviewNotification = $payloads->firstWhere('type', 'review_received');

    expect($applicationNotification['body'])->toContain($applicantName)
        ->and($reviewNotification['body'])->toContain('Bob Demo');
});
