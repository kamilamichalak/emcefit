<?php

namespace Tests\Feature\Client;

use App\Domain\Memberships\Actions\ResolveClosedMembershipVariant;
use App\Domain\Scheduling\Enums\ClassOccurrenceStatus;
use App\Domain\Scheduling\Models\ClassGroup;
use App\Domain\Scheduling\Models\ClassSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\ClassTypeSeeder;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ShortenedVariantEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    /** Poniedziałki czerwca 2026 — 5 kolejnych tygodni ISO. */
    private const MONDAYS = ['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ClassTypeSeeder::class);
        $this->seed(MembershipTypeSeeder::class);
        // czerwiec 2026 ma być "bieżący" — Prompt 10h pomija minione wystąpienia
        CarbonImmutable::setTestNow('2026-06-01 08:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function month(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-06-01');
    }

    /**
     * Tworzy $count grup poniedziałkowych i po jednym wystąpieniu na każdy
     * poniedziałek czerwca.
     *
     * @return array{groups: list<int>, occurrences: Collection<int, ClassSchedule>}
     */
    private function mondayGroups(int $count): array
    {
        $groups = [];
        $occurrences = collect();

        for ($i = 0; $i < $count; $i++) {
            $group = ClassGroup::factory()->forMonth($this->month())->create([
                'weekday' => 1,
                'start_time' => sprintf('1%d:00', $i),
            ]);
            $groups[] = $group->id;

            foreach (self::MONDAYS as $date) {
                $occurrences->push(ClassSchedule::factory()->create([
                    'class_group_id' => $group->id,
                    'date' => $date,
                    'start_time' => $group->start_time,
                ]));
            }
        }

        return ['groups' => $groups, 'occurrences' => $occurrences];
    }

    /**
     * Id wystąpień w podanych dniach (porównanie po dacie, bez godziny/strefy).
     *
     * @param  Collection<int, ClassSchedule>  $occurrences
     * @param  list<string>  $dates
     * @return list<int>
     */
    private function idsOn($occurrences, array $dates): array
    {
        return $occurrences
            ->filter(fn (ClassSchedule $o): bool => in_array($o->date->toDateString(), $dates, true))
            ->pluck('id')
            ->all();
    }

    private function resolve(): ResolveClosedMembershipVariant
    {
        return app(ResolveClosedMembershipVariant::class);
    }

    public function test_full_attendance_keeps_the_monthly_variant(): void
    {
        ['groups' => $groups] = $this->mondayGroups(3);

        $variant = $this->resolve()->handle($groups, $this->month(), []);

        $this->assertSame('Zamknięty 3x/tydzień — miesięczny', $variant->type->name);
        $this->assertFalse($variant->shortened);
        $this->assertNull($variant->firstEntryDate);
        $this->assertNull($variant->endDate);
        $this->assertSame(5, $variant->totalWeeks);
        $this->assertSame(5, $variant->attendanceWeeks);
    }

    public function test_skipping_whole_weeks_picks_a_shorter_pack(): void
    {
        ['groups' => $groups, 'occurrences' => $occurrences] = $this->mondayGroups(3);

        // pomiń całe tygodnie 2026-06-15 i 2026-06-22 (wszystkie 3 grupy) → 3 tygodnie obecności
        $absent = $this->idsOn($occurrences, ['2026-06-15', '2026-06-22']);

        $variant = $this->resolve()->handle($groups, $this->month(), $absent);

        $this->assertSame('Zamknięty 3x/tydzień — 3 tygodnie', $variant->type->name);
        $this->assertSame('170.00', $variant->type->price);
        $this->assertTrue($variant->shortened);
        $this->assertSame(5, $variant->totalWeeks);
        $this->assertSame(3, $variant->attendanceWeeks);
        $this->assertSame('2026-06-01', $variant->firstEntryDate->toDateString());
        $this->assertSame('2026-06-29', $variant->endDate->toDateString());
    }

    public function test_non_consecutive_skipped_weeks_span_the_full_gap(): void
    {
        ['groups' => $groups, 'occurrences' => $occurrences] = $this->mondayGroups(3);

        // obecność tylko w tygodniach 1 i 4 — pomijamy 2, 3 oraz 5
        $absent = $this->idsOn($occurrences, ['2026-06-08', '2026-06-15', '2026-06-29']);

        $variant = $this->resolve()->handle($groups, $this->month(), $absent);

        $this->assertSame('Zamknięty 3x/tydzień — 2 tygodnie', $variant->type->name);
        $this->assertSame(2, $variant->attendanceWeeks);
        // data_do = ostatnie "będę" (2026-06-22), a NIE "pierwsze wejście + 2 tygodnie"
        $this->assertSame('2026-06-01', $variant->firstEntryDate->toDateString());
        $this->assertSame('2026-06-22', $variant->endDate->toDateString());
    }

    public function test_partial_absence_within_a_week_stays_monthly(): void
    {
        ['groups' => $groups, 'occurrences' => $occurrences] = $this->mondayGroups(3);

        // tylko 1 z 3 zajęć w tygodniu 2026-06-15
        $absent = [$this->idsOn($occurrences, ['2026-06-15'])[0]];

        $variant = $this->resolve()->handle($groups, $this->month(), $absent);

        $this->assertSame('Zamknięty 3x/tydzień — miesięczny', $variant->type->name);
        $this->assertFalse($variant->shortened);
        $this->assertSame(5, $variant->attendanceWeeks);
    }

    public function test_no_matching_shorter_pack_falls_back_to_monthly(): void
    {
        ['groups' => $groups, 'occurrences' => $occurrences] = $this->mondayGroups(1);

        $absent = $this->idsOn($occurrences, ['2026-06-15']);

        $variant = $this->resolve()->handle($groups, $this->month(), $absent);

        // cennik nie ma "1x/tydzień — N tygodni"
        $this->assertSame('Zamknięty 1x/tydzień — miesięczny', $variant->type->name);
        $this->assertFalse($variant->shortened);
        $this->assertTrue($variant->fellBackToMonthly);
    }

    public function test_week_fully_cancelled_by_the_club_is_ignored(): void
    {
        ['groups' => $groups, 'occurrences' => $occurrences] = $this->mondayGroups(3);

        // klub odwołuje cały tydzień 2026-06-22
        $occurrences
            ->filter(fn (ClassSchedule $o): bool => $o->date->toDateString() === '2026-06-22')
            ->each(fn (ClassSchedule $o) => $o->update([
                'status' => ClassOccurrenceStatus::Cancelled,
                'cancellation_reason' => 'Remont',
            ]));

        // klient nie zaznacza żadnej własnej nieobecności
        $variant = $this->resolve()->handle($groups, $this->month(), []);

        // odwołany tydzień nie liczy się do żadnej strony → 4 tygodnie, pełna obecność
        $this->assertSame(4, $variant->totalWeeks);
        $this->assertSame(4, $variant->attendanceWeeks);
        $this->assertSame('Zamknięty 3x/tydzień — miesięczny', $variant->type->name);
        $this->assertFalse($variant->shortened);
    }
}
