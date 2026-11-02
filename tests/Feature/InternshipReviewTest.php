<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\InternshipLogbook;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

test('reviewers can approve selected logbooks at their own stage with an audit entry for each', function (string $reviewer, string $state, LogbookStatus $approvedStatus, string $timestamp) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    $entries = InternshipLogbook::factory()->for($placement, 'placement')->$state()->count(3)
        ->sequence(fn (Sequence $sequence): array => ['work_date' => now()->subDays($sequence->index)->toDateString()])
        ->create();
    $user = $placement->{$reviewer};

    Livewire::actingAs($user)->test('pages::internships.reviews')
        ->set('selectedLogbookIds', [(string) $entries[1]->id, (string) $entries[0]->id])
        ->call('approveSelected')->assertHasNoErrors()->assertSet('selectedLogbookIds', []);

    foreach ($entries->take(2) as $entry) {
        $this->assertDatabaseHas('internship_logbooks', [
            'id' => $entry->id,
            'status' => $approvedStatus->value,
            $timestamp => '2026-10-31 10:00:00',
        ]);
        $this->assertDatabaseHas('logbook_reviews', [
            'internship_logbook_id' => $entry->id,
            'reviewer_id' => $user->id,
            'from_status' => $entry->status->value,
            'to_status' => $approvedStatus->value,
        ]);
    }

    $this->assertDatabaseHas('internship_logbooks', ['id' => $entries[2]->id, 'status' => $entries[2]->status->value]);
    $this->assertDatabaseCount('logbook_reviews', 2);
})->with([
    'mentor approval' => ['mentor', 'pending', LogbookStatus::MentorApproved, 'mentor_approved_at'],
    'lecturer verification' => ['lecturer', 'mentorApproved', LogbookStatus::Verified, 'verified_at'],
]);

test('a batch containing another reviewers logbook changes none of the selected records', function (string $reviewer, string $state) {
    $this->freezeTime();
    $owned = InternshipLogbook::factory()->$state()->create();
    $foreign = InternshipLogbook::factory()->$state()->create();

    Livewire::actingAs($owned->placement->{$reviewer})->test('pages::internships.reviews')
        ->set('selectedLogbookIds', [$owned->id, $foreign->id])
        ->call('approveSelected')->assertNotFound();

    $this->assertDatabaseHas('internship_logbooks', ['id' => $owned->id, 'status' => $owned->status->value]);
    $this->assertDatabaseHas('internship_logbooks', ['id' => $foreign->id, 'status' => $foreign->status->value]);
    $this->assertDatabaseCount('logbook_reviews', 0);
})->with([
    'mentor' => ['mentor', 'pending'],
    'lecturer' => ['lecturer', 'mentorApproved'],
]);

test('a batch cannot skip stages or partially approve when one logbook is no longer reviewable', function (string $reviewer, string $state, LogbookStatus $invalidStatus) {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $ready = InternshipLogbook::factory()->for($placement, 'placement')->$state()->create();
    $unavailable = InternshipLogbook::factory()->for($placement, 'placement')->create([
        'work_date' => now()->subDay()->toDateString(),
        'status' => $invalidStatus,
    ]);

    Livewire::actingAs($placement->{$reviewer})->test('pages::internships.reviews')
        ->set('selectedLogbookIds', [$ready->id, $unavailable->id])
        ->call('approveSelected')->assertForbidden();

    $this->assertDatabaseHas('internship_logbooks', ['id' => $ready->id, 'status' => $ready->status->value]);
    $this->assertDatabaseHas('internship_logbooks', ['id' => $unavailable->id, 'status' => $invalidStatus->value]);
    $this->assertDatabaseCount('logbook_reviews', 0);
})->with([
    'mentor cannot reapprove' => ['mentor', 'pending', LogbookStatus::MentorApproved],
    'lecturer cannot bypass mentor' => ['lecturer', 'mentorApproved', LogbookStatus::PendingMentor],
    'verified entry is locked' => ['lecturer', 'mentorApproved', LogbookStatus::Verified],
]);

test('bulk approval rejects invalid selections before recording a decision', function (string $selection, string $field, string $message) {
    $this->freezeTime();
    $entry = InternshipLogbook::factory()->pending()->create();
    $ids = match ($selection) {
        'empty' => [],
        'duplicate' => [$entry->id, $entry->id],
        'invalid' => ['not-a-logbook'],
        'oversized' => range($entry->id, $entry->id + 50),
    };

    Livewire::actingAs($entry->placement->mentor)->test('pages::internships.reviews')
        ->set('selectedLogbookIds', $ids)->call('approveSelected')
        ->assertHasErrors([$field])->assertSee($message);

    $this->assertDatabaseHas('internship_logbooks', ['id' => $entry->id, 'status' => LogbookStatus::PendingMentor->value]);
    $this->assertDatabaseCount('logbook_reviews', 0);
})->with([
    'empty selection' => ['empty', 'selectedLogbookIds', 'Pilih logbook yang akan divalidasi.'],
    'duplicate selection' => ['duplicate', 'selectedLogbookIds.1', 'Logbook yang sama tidak boleh dipilih lebih dari sekali.'],
    'invalid identifier' => ['invalid', 'selectedLogbookIds.0', 'Pilihan logbook tidak valid. Pilih ulang dari daftar.'],
    'batch limit' => ['oversized', 'selectedLogbookIds', 'Pilih maksimal 50 logbook dalam satu proses.'],
]);

test('bulk approval rechecks the reviewer role after the page has been opened', function () {
    $this->freezeTime();
    $entry = InternshipLogbook::factory()->pending()->create();
    $mentor = $entry->placement->mentor;
    $page = Livewire::actingAs($mentor)->test('pages::internships.reviews')
        ->set('selectedLogbookIds', [$entry->id]);
    /** Simulate an external role change to recheck an already-open review page. */
    User::query()->whereKey($mentor->id)->update(['role' => UserRole::Mahasiswa->value]);
    $mentor->refresh();

    $page->call('approveSelected')->assertForbidden();

    $this->assertDatabaseHas('internship_logbooks', ['id' => $entry->id, 'status' => LogbookStatus::PendingMentor->value]);
    $this->assertDatabaseCount('logbook_reviews', 0);
});

test('the reviewer page defaults to the queue for the current role', function (UserRole $role, LogbookStatus $status) {
    $user = User::factory()->create(['role' => $role]);

    Livewire::actingAs($user)->test('pages::internships.reviews')
        ->assertOk()->assertSet('status', $status->value);
})->with([
    'mentor queue' => [UserRole::MentorDudi, LogbookStatus::PendingMentor],
    'lecturer queue' => [UserRole::Dosen, LogbookStatus::MentorApproved],
]);

test('an empty status in the navigation URL opens the complete logbook history', function () {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $verified = InternshipLogbook::factory()->for($placement, 'placement')->verified()->create(['description' => 'Aktivitas terverifikasi yang tetap tersedia di riwayat.']);
    $pending = InternshipLogbook::factory()->for($placement, 'placement')->pending()->create([
        'work_date' => now()->subDay()->toDateString(),
        'description' => 'Aktivitas baru yang menunggu pemeriksaan mentor.',
    ]);

    Livewire::actingAs($placement->mentor)->withQueryParams(['status' => ''])->test('pages::internships.reviews')
        ->assertOk()->assertSet('status', '')->assertSee($verified->description)->assertSee($pending->description);
});

test('review filters are restored from the URL and include the last day of the selected month', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    $included = InternshipLogbook::factory()->for($placement, 'placement')->verified()->create(['description' => 'Hasil verifikasi pada tanggal terakhir bulan Oktober.']);
    $previousMonth = InternshipLogbook::factory()->for($placement, 'placement')->verified()->create([
        'work_date' => '2026-09-30',
        'description' => 'Aktivitas September tidak termasuk filter Oktober.',
    ]);
    $otherPlacement = InternshipPlacement::factory()->create(['mentor_id' => $placement->mentor_id]);
    $otherEntry = InternshipLogbook::factory()->for($otherPlacement, 'placement')->verified()->create(['description' => 'Aktivitas mahasiswa dampingan lain.']);

    Livewire::actingAs($placement->mentor)->withQueryParams([
        'status' => LogbookStatus::Verified->value,
        'month' => '2026-10',
        'placementFilter' => (string) $placement->id,
    ])->test('pages::internships.reviews')->assertOk()
        ->assertSee($included->description)->assertDontSee($previousMonth->description)->assertDontSee($otherEntry->description);
});

test('changing review filters clears the selection and open detail', function (string $property, ?string $value) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $entry = InternshipLogbook::factory()->pending()->create();
    $page = Livewire::actingAs($entry->placement->mentor)->test('pages::internships.reviews')
        ->call('selectLogbook', $entry->id)->set('selectedLogbookIds', [$entry->id]);

    $page->set($property, $value ?? (string) $entry->internship_placement_id)
        ->assertSet('selectedLogbookIds', [])->assertSet('selectedId', null)->assertSet('feedback', '');

    $this->assertDatabaseCount('logbook_reviews', 0);
})->with([
    'status' => ['status', LogbookStatus::Verified->value],
    'month' => ['month', '2026-09'],
    'placement' => ['placementFilter', null],
]);

test('pagination clears hidden selections and a completed batch returns to a populated queue', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    $entries = InternshipLogbook::factory()->for($placement, 'placement')->pending()->count(13)
        ->sequence(fn (Sequence $sequence): array => ['work_date' => now()->subDays($sequence->index)->toDateString()])
        ->create();
    $page = Livewire::actingAs($placement->mentor)->test('pages::internships.reviews')
        ->set('selectedLogbookIds', [$entries->last()->id]);

    $page->call('nextPage')->assertSet('selectedLogbookIds', [])
        ->set('selectedLogbookIds', [$entries->first()->id])->call('approveSelected')
        ->assertHasNoErrors()->assertSet('paginators.page', 1)->assertSet('selectedLogbookIds', [])
        ->assertCount('entries', 12);

    $this->assertDatabaseHas('internship_logbooks', ['id' => $entries->first()->id, 'status' => LogbookStatus::MentorApproved->value]);
    $this->assertDatabaseHas('internship_logbooks', ['id' => $entries->last()->id, 'status' => LogbookStatus::PendingMentor->value]);
    $this->assertDatabaseCount('logbook_reviews', 1);
});

test('reviewing one logbook clears the batch selection without approving the other logbooks', function () {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $selected = InternshipLogbook::factory()->for($placement, 'placement')->pending()->create();
    $other = InternshipLogbook::factory()->for($placement, 'placement')->pending()->create(['work_date' => now()->subDay()->toDateString()]);
    $page = Livewire::actingAs($placement->mentor)->test('pages::internships.reviews')
        ->set('selectedLogbookIds', [$selected->id, $other->id])->call('selectLogbook', $selected->id);

    $page->call('review', 'approve')->assertHasNoErrors()
        ->assertSet('selectedLogbookIds', [])->assertSee(LogbookStatus::MentorApproved->label());

    $this->assertDatabaseHas('internship_logbooks', ['id' => $selected->id, 'status' => LogbookStatus::MentorApproved->value]);
    $this->assertDatabaseHas('internship_logbooks', ['id' => $other->id, 'status' => LogbookStatus::PendingMentor->value]);
    $this->assertDatabaseCount('logbook_reviews', 1);
});
