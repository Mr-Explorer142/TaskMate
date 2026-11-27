<?php

use App\Models\Task;
use Database\Seeders\DatabaseSeeder;

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
