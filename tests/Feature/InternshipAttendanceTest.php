<?php

use App\Models\InternshipAttendance;
use App\Models\InternshipPlacement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('a student can check in and out using server time and the assigned site', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    $component = Livewire::actingAs($placement->student)->test('pages::internships.logbook')
        ->call('recordAttendance', 'in', ['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 5, 'distance' => 0, 'checked_in_at' => '2000-01-01'])
        ->assertHasNoErrors();
    $this->assertDatabaseHas('internship_attendances', ['internship_placement_id' => $placement->id, 'checked_in_at' => '2026-10-31 01:00:00', 'checked_out_at' => null]);

    expect(InternshipAttendance::sole()->work_date->toDateString())->toBe('2026-10-31');
    $this->travel(8)->hours();
    $component->call('recordAttendance', 'out', ['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 5])->assertHasNoErrors();
    expect(InternshipAttendance::sole()->elapsedMinutes())->toBe(480);
    $this->assertDatabaseHas('internship_attendances', ['checked_out_at' => '2026-10-31 09:00:00']);
});

test('invalid or outside GPS never records attendance', function (array $coordinates, string $error) {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')->call('recordAttendance', 'in', $coordinates)->assertHasErrors([$error]);
    $this->assertDatabaseCount('internship_attendances', 0);
})->with([
    'outside radius with a forged distance' => [['latitude' => -6.21, 'longitude' => 106.8166667, 'accuracy' => 5, 'distance' => 0], 'location'],
    'outside longitude' => [['latitude' => -6.2, 'longitude' => 106.82, 'accuracy' => 5], 'location'],
    'just outside 100 meters' => [['latitude' => -6.19909, 'longitude' => 106.8166667, 'accuracy' => 5], 'location'],
    'poor accuracy' => [['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 101], 'accuracy'],
    'zero accuracy' => [['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 0], 'accuracy'],
    'invalid coordinate' => [['latitude' => 100, 'longitude' => 106.8166667, 'accuracy' => 5], 'latitude'],
    'missing location' => [[], 'latitude'],
]);

test('a point just inside the configured radius is accepted', function () {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')
        ->call('recordAttendance', 'in', ['latitude' => -6.19911, 'longitude' => 106.8166667, 'accuracy' => 5])->assertHasNoErrors();
    expect(InternshipAttendance::sole()->check_in_distance)->toBeGreaterThan(98)->toBeLessThan(100);
});

test('duplicate check ins and check outs without arrival are rejected', function () {
    $this->freezeTime();
    $placement = InternshipPlacement::factory()->create();
    $coordinates = ['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 5];
    $component = Livewire::actingAs($placement->student)->test('pages::internships.logbook');
    $component->call('recordAttendance', 'out', $coordinates)->assertHasErrors(['attendance']);
    $this->assertDatabaseCount('internship_attendances', 0);
    $component->call('recordAttendance', 'in', $coordinates)->assertHasNoErrors();
    $component->call('recordAttendance', 'in', $coordinates)->assertHasErrors(['attendance']);
    $this->assertDatabaseCount('internship_attendances', 1);
});

test('check out also requires an in radius location', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 16:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    $attendance = InternshipAttendance::factory()->for($placement, 'placement')->create();
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')->call('recordAttendance', 'out', ['latitude' => -6.3, 'longitude' => 106.8166667, 'accuracy' => 5])->assertHasErrors(['location']);
    expect($attendance->fresh()->checked_out_at)->toBeNull();
});

test('attendance cannot be recorded outside the internship dates', function (string $startsOn, string $endsOn) {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 08:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create(['starts_on' => $startsOn, 'ends_on' => $endsOn]);
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')->call('recordAttendance', 'in', ['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 5])->assertHasErrors(['attendance']);
    $this->assertDatabaseCount('internship_attendances', 0);
})->with([['2026-11-01', '2026-12-31'], ['2026-09-01', '2026-10-30']]);

test('attendance uses the site timezone and supports an overnight shift', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 15:30:00', 'UTC'));
    $placement = InternshipPlacement::factory()->create(['timezone' => 'Asia/Jayapura']);
    $coordinates = ['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 5];
    $component = Livewire::actingAs($placement->student)->test('pages::internships.logbook');
    $component->call('recordAttendance', 'in', $coordinates)->assertHasNoErrors();
    expect(InternshipAttendance::sole()->work_date->toDateString())->toBe('2026-11-01');
    $this->travel(8)->hours();
    $component->call('recordAttendance', 'out', $coordinates)->assertHasNoErrors();
    expect(InternshipAttendance::sole()->elapsedMinutes())->toBe(480);
});

test('students cannot select another students placement', function () {
    $placement = InternshipPlacement::factory()->create();
    $other = User::factory()->create();
    Livewire::actingAs($other)->test('pages::internships.logbook')->set('placementId', $placement->id)->assertNotFound();
    $this->assertDatabaseCount('internship_attendances', 0);
});

test('a completed day cannot be recorded twice', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 17:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    InternshipAttendance::factory()->for($placement, 'placement')->completed()->create();
    $coordinates = ['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 5];
    $page = Livewire::actingAs($placement->student)->test('pages::internships.logbook');
    $page->call('recordAttendance', 'in', $coordinates)->assertHasErrors(['attendance']);
    $page->call('recordAttendance', 'out', $coordinates)->assertHasErrors(['attendance']);
    $this->assertDatabaseCount('internship_attendances', 1);
});

test('a missed checkout from a past day does not block new attendance indefinitely', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 09:00', 'Asia/Jakarta'));
    $placement = InternshipPlacement::factory()->create();
    $old = InternshipAttendance::factory()->for($placement, 'placement')->create(['work_date' => '2026-10-30', 'checked_in_at' => CarbonImmutable::parse('2026-10-30 08:00', 'Asia/Jakarta')->utc()]);
    Livewire::actingAs($placement->student)->test('pages::internships.logbook')->assertSee('Presensi datang')
        ->call('recordAttendance', 'in', ['latitude' => -6.2, 'longitude' => 106.8166667, 'accuracy' => 5])->assertHasNoErrors();
    $this->assertDatabaseCount('internship_attendances', 2);
    expect($old->fresh()->checked_out_at)->toBeNull();
});
