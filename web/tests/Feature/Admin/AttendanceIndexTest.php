<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\Holiday;
use App\Models\Lesson;
use App\Models\LessonPlan;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use Database\Seeders\LessonPlanSeeder;
use Database\Seeders\SchoolClassSeeder;
use Database\Seeders\SectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 出席記録・一覧画面（admin.attendances.index）のテスト。
 * 表示する日付の決め方と、日付の切り替え（◀ ▶・選べる授業日の範囲）、日付選択のカレンダー（表示中の日付の年度の授業日・週ごとの第何回か・休校の週）、補講先の候補、授業計画上の第何回か、授業実施単位ごとのブロック・生徒の行の組み立て、出欠サマリーを確認する。
 * 仕様はルーティング設計の「出席記録・一覧画面の詳細」、要件定義書5.4を参照。
 */
class AttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // lessonsの外部キー（lesson_plan_id・section_id）に必要なマスタだけを登録する
        $this->seed([SectionSeeder::class, SchoolClassSeeder::class, LessonPlanSeeder::class]);

        $this->staff = Staff::factory()->create();

        // 「今日」を2026-09-26（土）に固定する。いつテストを実行しても同じ結果になるようにするため。
        // travelTo()で動かした時刻は、テストの終了時にLaravelが自動で元に戻す。
        $this->travelTo(Carbon::parse('2026-09-26 10:00:00'));
    }

    public function test_specified_date_is_used()
    {
        $this->createLessonOn('2026-09-19');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index', ['date' => '2026-09-19']));

        $response->assertInertia(fn ($page) => $page
            ->component('admin/attendances/index')
            ->where('date', '2026-09-19')
        );
    }

    public function test_specified_date_is_used_even_if_there_are_no_lessons_on_that_day()
    {
        // 授業のない日を指定した場合も、直近の授業日に置き換えずにその日付のまま表示する
        $this->createLessonOn('2026-09-19');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index', ['date' => '2026-09-20']));

        $response->assertInertia(fn ($page) => $page->where('date', '2026-09-20'));
    }

    public function test_today_is_used_when_there_are_lessons_today()
    {
        $this->createLessonOn('2026-09-19');
        $this->createLessonOn('2026-09-26');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index'));

        $response->assertInertia(fn ($page) => $page->where('date', '2026-09-26'));
    }

    public function test_latest_past_lesson_date_is_used_when_there_are_no_lessons_today()
    {
        // 今日（9/26）より後の授業日（9/30）は対象外。今日以前で最も新しい9/23になる
        $this->createLessonOn('2026-09-16');
        $this->createLessonOn('2026-09-23');
        $this->createLessonOn('2026-09-30');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index'));

        $response->assertInertia(fn ($page) => $page->where('date', '2026-09-23'));
    }

    public function test_today_is_used_when_there_are_no_past_lessons()
    {
        // 授業日が今日より後にしか無い場合（年度開始前など）は、今日の日付のまま表示する
        $this->createLessonOn('2026-09-30');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index'));

        $response->assertInertia(fn ($page) => $page->where('date', '2026-09-26'));
    }

    public function test_invalid_date_is_rejected()
    {
        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index', ['date' => '2026/09/26']));

        $response->assertSessionHasErrors('date');
    }

    // ------------------------------------------------------------
    // 日付の切り替え（◀ ▶）と、選べる授業日の範囲
    // ------------------------------------------------------------

    public function test_prev_and_next_dates_are_the_nearest_selectable_lesson_days()
    {
        // 今日は9/26（土）。5週間後は10/31（土）。
        // 記録が無くても5週間後（10/31）までの授業日は選べ、それより後の11/7は選べない
        $this->createLessonOn('2026-09-23');
        $this->createLessonOn('2026-11-07');
        $this->createLessonOn('2026-10-31');
        $this->createLessonOn('2026-09-16');
        $this->createLessonOn('2026-09-26');

        $response = $this->getAttendances('2026-09-23');

        $response->assertInertia(fn ($page) => $page
            ->where('prevDate', '2026-09-16')
            ->where('nextDate', '2026-09-26')
            ->where('firstSelectableDate', '2026-09-16')
            ->where('lastSelectableDate', '2026-10-31')
        );
    }

    public function test_prev_and_next_dates_are_null_at_both_ends()
    {
        $this->createLessonOn('2026-09-16');
        $this->createLessonOn('2026-10-31');
        $this->createLessonOn('2026-11-07'); // 5週間後より先で、記録が無い授業日

        $this->getAttendances('2026-09-16')->assertInertia(fn ($page) => $page
            ->where('prevDate', null)
            ->where('nextDate', '2026-10-31')
        );
        $this->getAttendances('2026-10-31')->assertInertia(fn ($page) => $page
            ->where('prevDate', '2026-09-16')
            ->where('nextDate', null)
        );
    }

    public function test_today_and_advance_until_are_passed()
    {
        // 事前に登録できる期限の目安は、今日（9/26）から5週間後の10/31
        $response = $this->getAttendances('2026-09-26');

        $response->assertInertia(fn ($page) => $page
            ->where('today', '2026-09-26')
            ->where('advanceUntil', '2026-10-31')
            ->where('advanceWeeks', 5)
        );
    }

    public function test_selectable_dates_are_null_when_there_are_no_lessons_up_to_five_weeks_later()
    {
        // 授業日が5週間後（10/31）より先にしか無く、記録も無い場合
        $this->createLessonOn('2026-11-07');

        $response = $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('prevDate', null)
            ->where('nextDate', null)
            ->where('firstSelectableDate', null)
            ->where('lastSelectableDate', null)
            ->where('calendar.lessonDates', [])
        );
    }

    public function test_lesson_days_after_five_weeks_with_records_are_selectable()
    {
        // 今日は9/26（土）。5週間後は10/31（土）。
        // 土曜A1の生徒が10/24を休み、11/7（5週間後より先）に30分前補講で来る予定。
        // 火曜A1の生徒からは、11/10（5週間後より先）の欠席の事前連絡が入っている
        $absentLesson = $this->createLesson('2026-10-24', 'A1', 'A1');
        $makeupLesson = $this->createLesson('2026-11-07', 'A1', 'A1');
        $noticeLesson = $this->createLesson('2026-11-10', 'A1', 'A1', '火');
        $this->createLesson('2026-11-14', 'A1', 'A1'); // 5週間後より先で、記録が無い授業日

        $this->createMakeup($this->createStudent('A1', 'A1', '年中', 'ア補講予定'), $absentLesson, $makeupLesson, '30分前補講');
        Attendance::create([
            'student_id' => $this->createStudent('A1', 'A1', '年中', 'イ事前連絡', weekday: '火')->id,
            'lesson_id' => $noticeLesson->id,
            'status' => '欠席',
            'is_late' => false,
            'makeup_type' => '未定',
        ]);

        // 補講で来る予定の日（11/7）と事前連絡がある日（11/10）は選べ、記録が無い日（11/14）は選べない
        $this->getAttendances('2026-10-24')->assertInertia(fn ($page) => $page
            ->where('nextDate', '2026-11-07')
            ->where('lastSelectableDate', '2026-11-10')
        );
        $this->getAttendances('2026-11-10')->assertInertia(fn ($page) => $page
            ->where('nextDate', null)
            ->where('calendar.lessonDates', ['2026-10-24', '2026-11-07', '2026-11-10'])
        );
    }

    // ------------------------------------------------------------
    // 日付選択のモーダルのカレンダー（表示中の日付の年度の分だけ）
    // ------------------------------------------------------------

    public function test_calendar_has_lesson_days_of_the_fiscal_year_in_ascending_order()
    {
        // 9/26は2026年度（2026/4〜2027/3）。3/30（月）〜2027/4/4（日）の週を出す
        $this->createLessonOn('2026-03-28'); // 範囲より前（2025年度）
        $this->createLessonOn('2026-09-26');
        $this->createLessonOn('2026-04-04');
        $this->createLessonOn('2026-10-31');
        $this->createLessonOn('2026-11-07'); // 5週間後より先で、記録が無い授業日

        $response = $this->getAttendances('2026-09-26');

        $response->assertInertia(fn ($page) => $page
            ->where('calendar.months', [
                '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09',
                '2026-10', '2026-11', '2026-12', '2027-01', '2027-02', '2027-03',
            ])
            ->where('calendar.lessonDates', ['2026-04-04', '2026-09-26', '2026-10-31'])
        );
    }

    public function test_calendar_of_january_to_march_is_the_fiscal_year_from_the_previous_april()
    {
        // 2026/2/7は2025年度（2025/4〜2026/3）。年度の終わりが今日（9/26）より前なので、5週間後までは延ばさない
        $this->createLessonOn('2026-02-07');
        $this->createLessonOn('2025-04-05');
        $this->createLessonOn('2026-04-11'); // 範囲より後（3/30〜4/5の週の次の週）

        $response = $this->getAttendances('2026-02-07');

        $response->assertInertia(fn ($page) => $page
            ->where('calendar.months.0', '2025-04')
            ->where('calendar.months.11', '2026-03')
            ->count('calendar.months', 12)
            ->where('calendar.lessonDates', ['2025-04-05', '2026-02-07'])
        );
    }

    public function test_calendar_is_extended_to_five_weeks_later_when_it_is_in_the_next_fiscal_year()
    {
        // 今日を2027/3/13（土）にする。5週間後は4/17（土）で、次の年度に入る
        $this->travelTo(Carbon::parse('2027-03-13 10:00:00'));
        $this->createLessonOn('2027-03-13');
        $this->createLessonOn('2027-04-17');
        $this->createLessonOn('2027-04-24'); // 5週間後より先で、記録が無い授業日

        $response = $this->getAttendances('2027-03-13');

        // 2026/4〜2027/4の13か月分
        $response->assertInertia(fn ($page) => $page
            ->where('calendar.months.0', '2026-04')
            ->where('calendar.months.12', '2027-04')
            ->count('calendar.months', 13)
            ->where('calendar.lessonDates', ['2027-03-13', '2027-04-17'])
        );
    }

    public function test_calendar_lesson_dates_have_no_duplicates_when_there_are_multiple_lessons_on_the_same_day()
    {
        // 9/19に、土曜A1と土曜B（B1・B2）の3件の授業回がある
        $this->createLesson('2026-09-19', 'A1', 'A1');
        $this->createLesson('2026-09-19', 'B', 'B1');
        $this->createLesson('2026-09-19', 'B', 'B2');

        $response = $this->getAttendances('2026-09-19');

        $response->assertInertia(fn ($page) => $page
            ->where('calendar.lessonDates', ['2026-09-19'])
        );
    }

    public function test_week_lesson_nos_are_keyed_by_monday_of_the_week()
    {
        // createLesson()は授業実施単位ごとに第1回から割り当てる。土曜A1の9/19が第1回、10/3が第2回
        $this->createLesson('2026-09-19', 'A1', 'A1');
        $this->createLesson('2026-10-03', 'A1', 'A1');

        $response = $this->getAttendances('2026-09-26');

        // キーはその週の月曜日。授業が無い週（9/21〜9/27）は入らない
        $response->assertInertia(fn ($page) => $page
            ->where('calendar.weekLessonNos', ['2026-09-14' => 1, '2026-09-28' => 2])
        );
    }

    public function test_week_lesson_no_is_the_smallest_when_they_differ_in_the_week()
    {
        // 土曜A1だけ先に9/26の授業回を作り、10/3を第2回にする（土曜Bの10/3は第1回のまま）
        $this->createLesson('2026-09-26', 'A1', 'A1');
        $this->createLesson('2026-10-03', 'A1', 'A1');
        $this->createLesson('2026-10-03', 'B', 'B1');

        $response = $this->getAttendances('2026-09-26');

        $response->assertInertia(fn ($page) => $page
            ->where('calendar.weekLessonNos', ['2026-09-21' => 1, '2026-09-28' => 1])
        );
    }

    public function test_holiday_weeks_are_keyed_by_monday_with_reasons()
    {
        // 文化の日の週（11/2〜11/7）。過去・未来を問わず出す
        foreach (['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07'] as $date) {
            Holiday::create(['section_id' => null, 'date' => $date, 'reason' => '文化の日']);
        }
        // 同じ週に別の理由の休校日がある場合は「・」でつなぐ
        Holiday::create(['section_id' => null, 'date' => '2026-09-21', 'reason' => '敬老の日']);
        Holiday::create(['section_id' => null, 'date' => '2026-09-22', 'reason' => '秋分の日']);
        // 授業がある週（10/12の週）は、休校日があっても休校の週にしない
        Holiday::create(['section_id' => null, 'date' => '2026-10-12', 'reason' => 'スポーツの日']);
        $this->createLesson('2026-10-17', 'A1', 'A1');
        // 授業実施単位ごとの休校は、全体の休校ではないので出さない
        Holiday::create([
            'section_id' => Section::where('name', 'A1')->where('weekday', '土')->firstOrFail()->id,
            'date' => '2026-11-28',
            'reason' => '教室の設備点検',
        ]);
        // 範囲（2026年度）より後
        Holiday::create(['section_id' => null, 'date' => '2027-05-04', 'reason' => 'ゴールデンウィーク']);

        $response = $this->getAttendances('2026-09-26');

        $response->assertInertia(fn ($page) => $page
            ->where('calendar.holidayWeeks', [
                '2026-09-21' => '敬老の日・秋分の日',
                '2026-11-02' => '文化の日',
            ])
        );
    }

    public function test_calendar_of_another_fiscal_year_is_returned_as_json()
    {
        // 2025/5を指定すると、2025年度（2025/4〜2026/3）の分を返す
        $this->createLesson('2025-05-10', 'A1', 'A1');
        $this->createLesson('2026-03-28', 'A1', 'A1');
        $this->createLesson('2026-09-26', 'A1', 'A1'); // 範囲より後
        Holiday::create(['section_id' => null, 'date' => '2025-05-03', 'reason' => '憲法記念日']);

        $response = $this->actingAs($this->staff, 'staff')
            ->getJson(route('admin.attendances.calendar', ['month' => '2025-05']));

        $response->assertOk()->assertExactJson([
            'months' => [
                '2025-04', '2025-05', '2025-06', '2025-07', '2025-08', '2025-09',
                '2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03',
            ],
            'lessonDates' => ['2025-05-10', '2026-03-28'],
            'weekLessonNos' => ['2025-05-05' => 1, '2026-03-23' => 2],
            'holidayWeeks' => ['2025-04-28' => '憲法記念日'],
        ]);
    }

    public function test_calendar_month_must_be_year_and_month()
    {
        foreach (['2026-13', '2026-10-01', 'abc'] as $month) {
            $this->actingAs($this->staff, 'staff')
                ->getJson(route('admin.attendances.calendar', ['month' => $month]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('month');
        }

        $this->actingAs($this->staff, 'staff')
            ->getJson(route('admin.attendances.calendar'))
            ->assertJsonValidationErrors('month');
    }

    public function test_is_future_tells_whether_the_date_has_not_come_yet()
    {
        $this->createLesson('2026-09-26', 'A1', 'A1');
        $this->createLesson('2026-10-03', 'A1', 'A1');

        // 今日（9/26）はまだ来ていない日ではない
        $this->getAttendances('2026-09-26')
            ->assertInertia(fn ($page) => $page->where('isFuture', false));

        $this->getAttendances('2026-10-03')
            ->assertInertia(fn ($page) => $page->where('isFuture', true));
    }

    // ------------------------------------------------------------
    // 今後の休校
    // ------------------------------------------------------------

    public function test_upcoming_holidays_are_grouped_into_periods()
    {
        // 今日は9/26（土）
        Holiday::create(['section_id' => null, 'date' => '2026-09-19', 'reason' => 'シルバーウィーク']); // 過去
        // 年末年始：1/3（日）は授業が無いため登録されていないが、続いている期間としてまとめる
        foreach (['2026-12-28', '2026-12-29', '2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02', '2027-01-04'] as $date) {
            Holiday::create(['section_id' => null, 'date' => $date, 'reason' => '年末年始']);
        }
        Holiday::create(['section_id' => null, 'date' => '2027-02-11', 'reason' => '建国記念の日']);
        // 授業実施単位ごとの休校は、全体の休校ではないので出さない
        Holiday::create([
            'section_id' => Section::where('name', 'A1')->where('weekday', '土')->firstOrFail()->id,
            'date' => '2026-11-07',
            'reason' => '教室の設備点検',
        ]);

        $response = $this->getAttendances('2026-09-26');

        $response->assertInertia(fn ($page) => $page
            ->where('upcomingHolidays', [
                ['reason' => '年末年始', 'from' => '2026-12-28', 'until' => '2027-01-04'],
                ['reason' => '建国記念の日', 'from' => '2027-02-11', 'until' => '2027-02-11'],
            ])
        );
    }

    // ------------------------------------------------------------
    // 補講先（振替・30分前補講）の候補
    // ------------------------------------------------------------

    public function test_basic_makeup_candidates_are_lessons_of_the_same_class_within_the_period()
    {
        // 10/3（土）の土曜A1（基礎クラス1組）を休んだ場合の候補を確認する。
        // createLesson()は授業実施単位ごとに第1回から順に授業内容を割り当てるため、
        // 土曜A1の10/3と火曜A1の9/29はどちらも第1回（同じ内容）、それ以外の土曜A1の回は別の内容になる
        $this->createLesson('2026-10-03', 'A1', 'A1');
        $this->createLesson('2026-09-26', 'A1', 'A1'); // 1週間前（期間の初日）
        $this->createLesson('2026-09-19', 'A1', 'A1'); // 期間より前
        $tuesday = $this->createLesson('2026-09-29', 'A1', 'A1', '火'); // 別の曜日・同じ内容
        $this->createLesson('2026-10-31', 'A1', 'A1'); // 4週間後（期間の最終日）
        $this->createLesson('2026-11-07', 'A1', 'A1'); // 期間より後
        $this->createLesson('2026-10-10', 'A2', 'A2'); // 別の授業クラス

        $response = $this->getAttendances('2026-10-03');

        // 休んだ授業回そのもの・期間外の回・別のクラスの回は含まず、日付順に並ぶ
        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.makeupCandidates', 3)
            ->where('blocks.0.makeupCandidates.0.date', '2026-09-26')
            ->where('blocks.0.makeupCandidates.0.lessonNo', 2)
            ->where('blocks.0.makeupCandidates.0.sameContent', false)
            ->where('blocks.0.makeupCandidates.1.lessonId', $tuesday->id)
            ->where('blocks.0.makeupCandidates.1.date', '2026-09-29')
            ->where('blocks.0.makeupCandidates.1.weekday', '火')
            ->where('blocks.0.makeupCandidates.1.title', '基礎クラス1組')
            ->where('blocks.0.makeupCandidates.1.lessonNo', 1)
            ->where('blocks.0.makeupCandidates.1.sameContent', true)
            ->where('blocks.0.makeupCandidates.2.date', '2026-10-31')
            ->where('blocks.0.makeupCandidates.2.lessonNo', 4)
        );
    }

    public function test_advanced_makeup_candidates_are_sessions_of_any_class()
    {
        // 10/3（土）の土曜BでB1クラスの生徒が休んだ場合。応用クラスは、どの組の回でも補講を受けられる
        $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createLesson('2026-10-03', 'B', 'B2'); // 休んだ回と同じ日・同じ時間（一緒に授業をする回）
        $wednesdayB3 = $this->createLesson('2026-10-07', 'B', 'B3', '水'); // その回はB3クラスの授業回しかない
        $nextB1 = $this->createLesson('2026-10-10', 'B', 'B1');
        $this->createLesson('2026-10-10', 'B', 'B2'); // 10/10の土曜Bは、B1とB2が一緒に授業をする
        $this->createLesson('2026-11-07', 'B', 'B1'); // 期間より後

        $response = $this->getAttendances('2026-10-03');

        // 10/3の土曜BにはB1・B2の授業回があるが、ブロックの候補は1つ。
        // 同じ日・同じ時間の授業回は1件にまとめ、組を付けず「応用クラス」と表示する。同じ内容の印は付けない
        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.makeupCandidates', 2)
            ->where('blocks.0.makeupCandidates.0.lessonId', $wednesdayB3->id)
            ->where('blocks.0.makeupCandidates.0.date', '2026-10-07')
            ->where('blocks.0.makeupCandidates.0.title', '応用クラス')
            ->where('blocks.0.makeupCandidates.0.lessonNo', 1)
            ->where('blocks.0.makeupCandidates.0.sameContent', false)
            ->where('blocks.0.makeupCandidates.1.lessonId', $nextB1->id)
            ->where('blocks.0.makeupCandidates.1.date', '2026-10-10')
            ->where('blocks.0.makeupCandidates.1.title', '応用クラス')
            ->where('blocks.0.makeupCandidates.1.sameContent', false)
        );
    }

    public function test_each_block_has_its_own_makeup_candidates()
    {
        // 10/3に土曜A1（10:00）と土曜B（14:30）の2ブロックがあり、それぞれのブロックの候補になる
        $this->createLesson('2026-10-03', 'A1', 'A1');
        $this->createLesson('2026-10-03', 'B', 'B1');
        $nextA1 = $this->createLesson('2026-10-10', 'A1', 'A1');
        $nextB = $this->createLesson('2026-10-10', 'B', 'B1');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.makeupCandidates', 1)
            ->where('blocks.0.makeupCandidates.0.lessonId', $nextA1->id)
            ->where('blocks.0.makeupCandidates.0.title', '基礎クラス1組')
            ->has('blocks.1.makeupCandidates', 1)
            ->where('blocks.1.makeupCandidates.0.lessonId', $nextB->id)
            ->where('blocks.1.makeupCandidates.0.title', '応用クラス')
        );
    }

    // ------------------------------------------------------------
    // 授業計画上の第何回か
    // ------------------------------------------------------------

    public function test_lesson_no_of_the_day_is_shown()
    {
        // createLesson()は授業実施単位ごとに第1回から割り当てる。土曜A1・土曜B（B1・B2）の10/3はどれも第1回
        $this->createLesson('2026-10-03', 'A1', 'A1');
        $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createLesson('2026-10-03', 'B', 'B2');

        $response = $this->getAttendances('2026-10-03');

        // 同じ回数は1つにまとまる
        $response->assertInertia(fn ($page) => $page->where('lessonNos', [1]));
    }

    public function test_lesson_nos_are_all_shown_when_they_differ_between_sections()
    {
        // 土曜A1だけ先に9/26の授業回を作り、10/3を第2回にする（土曜Bの10/3は第1回のまま）。
        // 休校は週単位で決まるためふつうは起きないが、授業実施単位ごとの休校日を登録した場合などにずれうる
        $this->createLesson('2026-09-26', 'A1', 'A1');
        $this->createLesson('2026-10-03', 'A1', 'A1');
        $this->createLesson('2026-10-03', 'B', 'B1');

        $response = $this->getAttendances('2026-10-03');

        // 小さい順にすべて並ぶ
        $response->assertInertia(fn ($page) => $page->where('lessonNos', [1, 2]));
    }

    public function test_lesson_nos_are_empty_on_a_day_without_lessons()
    {
        $this->createLesson('2026-10-03', 'A1', 'A1');

        $response = $this->getAttendances('2026-10-04');

        $response->assertInertia(fn ($page) => $page->where('lessonNos', []));
    }

    // ------------------------------------------------------------
    // ブロック（授業実施単位ごとのまとまり）
    // ------------------------------------------------------------

    public function test_blocks_are_ordered_by_start_time()
    {
        // 開始時刻が遅い応用クラス（土曜B 14:30）を先に作り、作成順ではなく開始時刻順に並ぶことを確認する
        $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createLesson('2026-10-03', 'B', 'B2');
        $this->createLesson('2026-10-03', 'A1', 'A1');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks', 2)
            ->where('blocks.0.title', '基礎クラス1組')
            ->where('blocks.0.startTime', '10:00')
            ->where('blocks.0.endTime', '11:00')
            ->where('blocks.1.startTime', '14:30')
            ->where('blocks.1.endTime', '16:00')
        );
    }

    public function test_block_title_drops_class_number_when_multiple_classes_share_a_section()
    {
        $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createLesson('2026-10-03', 'B', 'B2');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks', 1)
            ->where('blocks.0.title', '応用クラス')
        );
    }

    public function test_block_title_is_class_name_when_only_one_class_uses_the_section()
    {
        $this->createLesson('2026-10-03', 'B', 'B1');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.title', '応用クラス1組')
        );
    }

    public function test_no_blocks_on_a_day_without_lessons()
    {
        $response = $this->getAttendances('2026-10-04');

        $response->assertInertia(fn ($page) => $page->has('blocks', 0));
    }

    // ------------------------------------------------------------
    // 生徒の行
    // ------------------------------------------------------------

    public function test_students_are_ordered_by_class_then_grade_then_name_kana()
    {
        $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createLesson('2026-10-03', 'B', 'B2');

        // 作成順・名前順だけでは期待する並びにならないよう、ばらばらの順で作る
        $this->createStudent('B', 'B2', '小4', 'アベ');
        $this->createStudent('B', 'B1', '小5', 'イトウ');
        $this->createStudent('B', 'B1', '小3', 'ウエダ');
        $this->createStudent('B', 'B1', '小3', 'アオキ');

        $response = $this->getAttendances('2026-10-03');

        // ①授業クラス順（B1→B2）→②学年順（小3→小5）→③名前カナ順（アオキ→ウエダ）
        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 4)
            ->where('blocks.0.rows.0.name', 'アオキ')
            ->where('blocks.0.rows.1.name', 'ウエダ')
            ->where('blocks.0.rows.2.name', 'イトウ')
            ->where('blocks.0.rows.3.name', 'アベ')
            ->where('blocks.0.rows.3.schoolClassName', '応用クラス2組')
        );
    }

    public function test_students_on_leave_are_excluded()
    {
        $this->createLesson('2026-10-03', 'A1', 'A1');

        $this->createStudent('A1', 'A1', '年中', 'ア休会なし');
        $this->createStudent('A1', 'A1', '年中', 'イ休会前', '2026-11-01', null);
        $this->createStudent('A1', 'A1', '年中', 'ウ休会終了済み', '2026-09-01', '2026-09-30');
        $this->createStudent('A1', 'A1', '年中', 'エ休会中', '2026-09-01', '2026-10-31');
        $this->createStudent('A1', 'A1', '年中', 'オ休会中終了未定', '2026-09-01', null);
        // 休会開始日・終了日がちょうど当日の場合も休会中として扱う
        $this->createStudent('A1', 'A1', '年中', 'カ休会開始日が当日', '2026-10-03', null);
        $this->createStudent('A1', 'A1', '年中', 'キ休会終了日が当日', '2026-09-01', '2026-10-03');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 3)
            ->where('blocks.0.rows.0.name', 'ア休会なし')
            ->where('blocks.0.rows.1.name', 'イ休会前')
            ->where('blocks.0.rows.2.name', 'ウ休会終了済み')
        );
    }

    public function test_students_without_their_class_lesson_on_that_day_are_excluded()
    {
        // 土曜Bの授業回はB1クラスの分しかない日
        $this->createLesson('2026-10-03', 'B', 'B1');

        $this->createStudent('B', 'B1', '小3', 'アB1の生徒');
        // 同じ授業実施単位だが、この日に自分のクラス（B2）の授業回が無い
        $this->createStudent('B', 'B2', '小4', 'イB2の生徒');
        // 授業回が無い別の授業実施単位（土曜A2）の生徒
        $this->createStudent('A2', 'A2', '小1', 'ウA2の生徒');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks', 1)
            ->has('blocks.0.rows', 1)
            ->where('blocks.0.rows.0.name', 'アB1の生徒')
        );
    }

    public function test_row_has_lesson_id_and_display_status_from_attendance()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');

        $present = $this->createStudent('A1', 'A1', '年中', 'ア出席');
        $this->createStudent('A1', 'A1', '年中', 'イ記録なし');

        Attendance::create([
            'student_id' => $present->id,
            'lesson_id' => $lesson->id,
            'status' => '出席',
            'is_late' => false,
        ]);

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.rows.0.studentId', $present->id)
            ->where('blocks.0.rows.0.nameKana', 'ア出席')
            ->where('blocks.0.rows.0.lessonId', $lesson->id)
            ->where('blocks.0.rows.0.lessonNo', 1)
            ->where('blocks.0.rows.0.displayStatus', '出席')
            ->where('blocks.0.rows.1.displayStatus', '未記録')
        );
    }

    public function test_regular_row_has_recorder_and_notice_flag()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');

        $recorder = Staff::factory()->create(['name' => '山田 太郎']);
        $deletedRecorder = Staff::factory()->create(['name' => '佐藤 花子']);
        $deletedRecorder->delete();

        // スタッフが記録した
        $recorded = $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'ア記録済み'), $lesson, '出席');
        $recorded->update(['staff_id' => $recorder->id]);
        // 保護者が事前連絡し、スタッフはまだ対応していない（staff_idが空）
        $notice = $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'イ事前連絡'), $lesson, '欠席');
        $notice->update(['note' => '発熱のため休みます']);
        // 記録が無い
        $this->createStudent('A1', 'A1', '年中', 'ウ記録なし');
        // 記録したスタッフが削除されている
        $byDeleted = $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'エ削除されたスタッフ'), $lesson, '出席');
        $byDeleted->update(['staff_id' => $deletedRecorder->id]);

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.rows.0.recordedBy', '山田 太郎')
            ->where('blocks.0.rows.0.isNotice', false)
            ->where('blocks.0.rows.0.note', null)
            ->where('blocks.0.rows.1.recordedBy', null)
            ->where('blocks.0.rows.1.isNotice', true)
            ->where('blocks.0.rows.1.note', '発熱のため休みます')
            ->where('blocks.0.rows.2.recordedBy', null)
            ->where('blocks.0.rows.2.isNotice', false)
            // 削除されたスタッフの名前も出す
            ->where('blocks.0.rows.3.recordedBy', '佐藤 花子')
            ->where('blocks.0.rows.3.isNotice', false)
        );
    }

    public function test_regular_row_has_makeup_plan_of_the_absence()
    {
        // 10/3（土）の土曜B（B1）を休み、10/7（水）の水曜Bに振替で行く予定
        $lesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $makeupLesson = $this->createLesson('2026-10-07', 'B', 'B1', '水');
        $planned = $this->createStudent('B', 'B1', '小3', 'ア振替予定');
        $done = $this->createStudent('B', 'B1', '小3', 'イ振替済み');
        $undecided = $this->createStudent('B', 'B1', '小3', 'ウ補講未定');
        $this->createStudent('B', 'B1', '小3', 'エ記録なし');
        $noMakeup = $this->createStudent('B', 'B1', '小3', 'オ補講なし');
        $this->createMakeup($planned, $lesson, $makeupLesson, '振替');
        $this->createMakeup($done, $lesson, $makeupLesson, '振替', '出席');
        Attendance::create([
            'student_id' => $undecided->id,
            'lesson_id' => $lesson->id,
            'status' => '欠席',
            'is_late' => false,
            'makeup_type' => '未定',
        ]);
        Attendance::create([
            'student_id' => $noMakeup->id,
            'lesson_id' => $lesson->id,
            'status' => '欠席',
            'is_late' => false,
            'makeup_type' => '補講なし',
        ]);

        $response = $this->getAttendances('2026-10-03');

        // 休んだ日の行にも、補講予定（補講先の日付と、補講先での出欠）を渡す
        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.rows.0.makeupType', '振替')
            ->where('blocks.0.rows.0.makeupDate', '2026-10-07')
            ->where('blocks.0.rows.0.makeupLessonId', $makeupLesson->id)
            ->where('blocks.0.rows.0.makeupStatus', null)
            ->where('blocks.0.rows.1.makeupType', '振替')
            ->where('blocks.0.rows.1.makeupDate', '2026-10-07')
            ->where('blocks.0.rows.1.makeupStatus', '出席')
            ->where('blocks.0.rows.2.makeupType', '未定')
            ->where('blocks.0.rows.2.makeupDate', null)
            ->where('blocks.0.rows.2.makeupLessonId', null)
            ->where('blocks.0.rows.3.makeupType', null)
            ->where('blocks.0.rows.3.makeupDate', null)
            ->where('blocks.0.rows.4.makeupType', '補講なし')
            ->where('blocks.0.rows.4.makeupDate', null)
        );
    }

    // ------------------------------------------------------------
    // 振替・30分前補講で「来る側」の行
    // ------------------------------------------------------------

    public function test_makeup_row_is_shown_in_the_block_of_the_makeup_lesson()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');

        // 水曜Bの生徒が、9/30（水）を休んで10/3（土）の土曜Bに振替で来る
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替で来る', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $makeup = $this->createMakeup($student, $absentLesson, $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 0)
            ->has('blocks.0.makeupRows', 1)
            ->where('blocks.0.makeupRows.0.kind', 'makeup')
            ->where('blocks.0.makeupRows.0.attendanceId', $makeup->id)
            ->where('blocks.0.makeupRows.0.studentId', $student->id)
            ->where('blocks.0.makeupRows.0.name', 'ア振替で来る')
            ->where('blocks.0.makeupRows.0.nameKana', 'ア振替で来る')
            ->where('blocks.0.makeupRows.0.schoolClassName', '応用クラス1組')
            ->where('blocks.0.makeupRows.0.lessonId', $makeupLesson->id)
            ->where('blocks.0.makeupRows.0.makeupTypes', ['振替'])
            ->where('blocks.0.makeupRows.0.absences', [['absentDate' => '2026-09-30', 'note' => null]])
            ->where('blocks.0.makeupRows.0.makeupStatus', null)
        );
    }

    public function test_makeup_rows_are_separated_from_regular_rows()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createStudent('B', 'B1', '小6', 'イ通常の生徒');

        // 授業クラス・学年・名前のどれで比べても通常の生徒より前に来る生徒でも、「来る側」は所属する生徒の行とは別に分ける
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替で来る', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 1)
            ->where('blocks.0.rows.0.kind', 'regular')
            ->where('blocks.0.rows.0.name', 'イ通常の生徒')
            ->where('blocks.0.rows.0.catchUp', null)
            ->has('blocks.0.makeupRows', 1)
            ->where('blocks.0.makeupRows.0.name', 'ア振替で来る')
        );
    }

    public function test_student_catching_up_in_own_class_is_shown_in_one_row()
    {
        // 9/26（土）を休み、自分のクラスの次の回（10/3）に30分前補講で来る
        $absentLesson = $this->createLesson('2026-09-26', 'A1', 'A1');
        $makeupLesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1', '年中', 'ア自分のクラスで補講');
        $this->createStudent('A1', 'A1', '年長', 'イ補講なしの生徒');
        $makeup = $this->createMakeup($student, $absentLesson, $makeupLesson, '30分前補講');
        $makeup->update(['note' => '次の回に30分早く行きます']);

        $response = $this->getAttendances('2026-10-03');

        // 補講の行は別に作らず、通常の行に補講の情報（catchUp）を持たせる
        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 2)
            ->where('blocks.0.rows.0.studentId', $student->id)
            ->where('blocks.0.rows.0.catchUp', [
                'attendanceId' => $makeup->id,
                'makeupTypes' => ['30分前補講'],
                'absences' => [['absentDate' => '2026-09-26', 'note' => '次の回に30分早く行きます']],
                'makeupStatus' => null,
            ])
            ->where('blocks.0.rows.1.catchUp', null)
            ->has('blocks.0.makeupRows', 0)
        );
    }

    public function test_one_hour_catch_up_in_own_class_is_shown_in_one_row()
    {
        // 9/19・9/26（土）の2回を休み、自分のクラスの10/3に、2回分の30分前補講（1時間前補講）で来る
        $absentLesson1 = $this->createLesson('2026-09-19', 'A1', 'A1');
        $absentLesson2 = $this->createLesson('2026-09-26', 'A1', 'A1');
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1', '年中', 'ア1時間前補講');
        // 新しい欠席の分から作っても、休んだ日の古い順に並ぶ
        $this->createMakeup($student, $absentLesson2, $lesson, '30分前補講');
        $makeup1 = $this->createMakeup($student, $absentLesson1, $lesson, '30分前補講');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 1)
            ->where('blocks.0.rows.0.catchUp', [
                'attendanceId' => $makeup1->id,
                'makeupTypes' => ['30分前補講', '30分前補講'],
                'absences' => [
                    ['absentDate' => '2026-09-19', 'note' => null],
                    ['absentDate' => '2026-09-26', 'note' => null],
                ],
                'makeupStatus' => null,
            ])
            ->has('blocks.0.makeupRows', 0)
            // 1人1行で数える
            ->where('blocks.0.summary', ['present' => 0, 'absent' => 0, 'unrecorded' => 1])
        );
    }

    public function test_two_makeups_of_a_student_from_another_class_are_shown_in_one_row()
    {
        // 水曜Bの生徒が、9/23分を振替、9/30分を30分前補講にして、10/3（土）の土曜Bにまとめて来る
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替と30分前補講', weekday: '水');
        $this->createMakeup($student, $this->createLesson('2026-09-30', 'B', 'B1', '水'), $makeupLesson, '30分前補講')
            ->update(['note' => '30分前補講もお願いします']);
        $transfer = $this->createMakeup($student, $this->createLesson('2026-09-23', 'B', 'B1', '水'), $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        // 振替を先、欠席の日は古い順にして1行にまとめる
        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.makeupRows', 1)
            ->where('blocks.0.makeupRows.0.attendanceId', $transfer->id)
            ->where('blocks.0.makeupRows.0.makeupTypes', ['振替', '30分前補講'])
            ->where('blocks.0.makeupRows.0.absences', [
                ['absentDate' => '2026-09-23', 'note' => null],
                ['absentDate' => '2026-09-30', 'note' => '30分前補講もお願いします'],
            ])
            ->where('blocks.0.makeupSummary', ['present' => 0, 'absent' => 0, 'unrecorded' => 1])
        );
    }

    public function test_student_of_the_same_section_without_own_class_lesson_is_shown_as_makeup_row()
    {
        // 10/3の土曜BはB1の授業回だけ。土曜BのB2の生徒は、その日に自分のクラスの授業回が無いので、所属する生徒の行には出ない。
        // 9/26分の30分前補講で10/3に来る場合は、行にまとめずに「来る側」の行に出す
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B2', '小4', 'ア同じ授業実施単位の別の組');
        $this->createMakeup($student, $this->createLesson('2026-09-26', 'B', 'B2'), $makeupLesson, '30分前補講');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.rows', 0)
            ->has('blocks.0.makeupRows', 1)
            ->where('blocks.0.makeupRows.0.studentId', $student->id)
        );
    }

    public function test_regular_row_has_other_makeups_of_the_student()
    {
        // 9/26・10/3（土）の2回を休み、どちらも10/10に30分前補講（1時間前補講）で来る予定
        $absentLesson = $this->createLesson('2026-09-26', 'A1', 'A1');
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $makeupLesson = $this->createLesson('2026-10-10', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1', '年中', 'ア2回休んだ');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '30分前補講');
        $this->createMakeup($student, $lesson, $makeupLesson, '30分前補講');
        $sessionKey = "2026-10-10-{$makeupLesson->section_id}";

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            // この回の欠席の補講先に入っている補講予定（この欠席の分も含む）
            ->where('blocks.0.rows.0.makeupGroupTypes', ['30分前補講', '30分前補講'])
            // ほかの欠席分の補講予定（この回の欠席の分は除く）
            ->where('blocks.0.rows.0.otherMakeups', [['sessionKey' => $sessionKey, 'makeupType' => '30分前補講']])
            // 補講先の候補にも、同じ形のキーを付ける
            ->where('blocks.0.makeupCandidates.1.sessionKey', $sessionKey)
        );
    }

    public function test_makeup_row_is_not_shown_in_other_blocks()
    {
        // 10/3に土曜A1（10:00）と土曜B（14:30）の2ブロックがあり、補講先は土曜Bだけ
        $this->createLesson('2026-10-03', 'A1', 'A1');
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替で来る', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks', 2)
            ->where('blocks.0.title', '基礎クラス1組')
            ->has('blocks.0.makeupRows', 0)
            ->has('blocks.1.makeupRows', 1)
        );
    }

    public function test_makeup_status_is_shown_on_makeup_row()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1', '小3', 'ア振替で来た', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '振替', '出席')
            ->update(['note' => '10/3の振替を希望します']);

        $response = $this->getAttendances('2026-10-03');

        // 元の欠席記録の連絡事項も、来る側の行に出す
        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.makeupRows.0.makeupStatus', '出席')
            ->where('blocks.0.makeupRows.0.absences.0.note', '10/3の振替を希望します')
        );
    }

    public function test_makeup_row_of_soft_deleted_student_is_not_shown()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $student = $this->createStudent('B', 'B1', '小3', 'ア削除された生徒', weekday: '水');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($student, $absentLesson, $makeupLesson, '振替');
        $student->delete();

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page->has('blocks.0.makeupRows', 0));
    }

    // ------------------------------------------------------------
    // 出欠サマリー
    // ------------------------------------------------------------

    public function test_regular_rows_are_counted_by_status()
    {
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');

        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'ア出席'), $lesson, '出席');
        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'イ遅刻して出席'), $lesson, '出席', isLate: true);
        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'ウ欠席'), $lesson, '欠席');
        $this->createStudent('A1', 'A1', '年中', 'エ記録なし');
        // 遅刻の連絡だけ来ていて、まだ来たかどうか記録していない
        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'オ遅刻連絡のみ'), $lesson, null, isLate: true);

        // 振替済みの生徒も、この日の記録としては「欠席」に数える
        $student = $this->createStudent('A1', 'A1', '年中', 'カ振替済み');
        $makeupLesson = $this->createLesson('2026-10-07', 'B', 'B1', '水');
        $this->createMakeup($student, $lesson, $makeupLesson, '振替', '出席');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.rows.0.status', '出席')
            ->where('blocks.0.rows.3.status', null)
            ->where('blocks.0.summary', ['present' => 2, 'absent' => 2, 'unrecorded' => 2])
        );
    }

    public function test_makeup_rows_are_counted_by_makeup_status()
    {
        $makeupLesson = $this->createLesson('2026-10-03', 'B', 'B1');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');

        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'ア補講に出席', weekday: '水'), $absentLesson, $makeupLesson, '振替', '出席');
        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'イ補講も欠席', weekday: '水'), $absentLesson, $makeupLesson, '振替', '欠席');
        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'ウ補講は未記録', weekday: '水'), $absentLesson, $makeupLesson, '振替');

        $response = $this->getAttendances('2026-10-03');

        $response->assertInertia(fn ($page) => $page
            ->has('blocks.0.makeupRows', 3)
            ->where('blocks.0.makeupSummary', ['present' => 1, 'absent' => 1, 'unrecorded' => 1])
            // 所属する生徒の出欠には数えない
            ->where('blocks.0.summary', ['present' => 0, 'absent' => 0, 'unrecorded' => 0])
        );
    }

    public function test_student_catching_up_in_own_class_is_unrecorded_until_the_catch_up_is_recorded()
    {
        // 自分のクラスの回に30分前補講で来る生徒。通常の授業は出席を記録済み、補講はまだ記録していない
        $absentLesson = $this->createLesson('2026-09-26', 'A1', 'A1');
        $lesson = $this->createLesson('2026-10-03', 'A1', 'A1');
        $student = $this->createStudent('A1', 'A1', '年中', 'ア自分のクラスで補講');
        $this->createAttendance($student, $lesson, '出席');
        $makeup = $this->createMakeup($student, $absentLesson, $lesson, '30分前補講');

        // 1人1行で数える。補講の記録し忘れが残らないよう、補講がまだなら未記録に数える
        $this->getAttendances('2026-10-03')->assertInertia(fn ($page) => $page
            ->where('blocks.0.summary', ['present' => 0, 'absent' => 0, 'unrecorded' => 1])
            ->where('blocks.0.makeupSummary', ['present' => 0, 'absent' => 0, 'unrecorded' => 0])
        );

        // 補講の出欠を記録すると、授業の出欠（出席）で数える
        $makeup->update(['makeup_status' => '欠席']);

        $this->getAttendances('2026-10-03')->assertInertia(fn ($page) => $page
            ->where('blocks.0.summary', ['present' => 1, 'absent' => 0, 'unrecorded' => 0])
        );
    }

    public function test_day_summary_is_total_of_regular_and_makeup_rows_of_all_blocks()
    {
        // 土曜A1：出席1・未記録1
        $lessonA1 = $this->createLesson('2026-10-03', 'A1', 'A1');
        $this->createAttendance($this->createStudent('A1', 'A1', '年中', 'ア出席'), $lessonA1, '出席');
        $this->createStudent('A1', 'A1', '年中', 'イ記録なし');

        // 土曜B：欠席1。補講で来る生徒は出席1・未記録1
        $lessonB = $this->createLesson('2026-10-03', 'B', 'B1');
        $this->createAttendance($this->createStudent('B', 'B1', '小3', 'ウ欠席'), $lessonB, '欠席');
        $absentLesson = $this->createLesson('2026-09-30', 'B', 'B1', '水');
        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'エ振替で来た', weekday: '水'), $absentLesson, $lessonB, '振替', '出席');
        $this->createMakeup($this->createStudent('B', 'B1', '小3', 'オ振替で来る', weekday: '水'), $absentLesson, $lessonB, '振替');

        $response = $this->getAttendances('2026-10-03');

        // その日の生徒全員（所属する生徒と、補講で来る生徒）を足す
        $response->assertInertia(fn ($page) => $page
            ->where('blocks.0.summary', ['present' => 1, 'absent' => 0, 'unrecorded' => 1])
            ->where('blocks.1.summary', ['present' => 0, 'absent' => 1, 'unrecorded' => 0])
            ->where('blocks.1.makeupSummary', ['present' => 1, 'absent' => 0, 'unrecorded' => 1])
            ->where('daySummary', ['present' => 2, 'absent' => 1, 'unrecorded' => 2])
        );
    }

    public function test_day_summary_is_zero_on_a_day_without_lessons()
    {
        $response = $this->getAttendances('2026-10-04');

        $response->assertInertia(fn ($page) => $page
            ->where('daySummary', ['present' => 0, 'absent' => 0, 'unrecorded' => 0])
        );
    }

    /**
     * 指定した日付の授業回を1件作る。
     * lessonsには(lesson_plan_id, section_id)の複合UNIQUE制約があるため、
     * まだ授業回を作っていない授業内容を毎回選んで重複しないようにしている。
     */
    private function createLessonOn(string $date): Lesson
    {
        $lessonPlan = LessonPlan::whereDoesntHave('lessons')->orderBy('id')->firstOrFail();

        return Lesson::create([
            'lesson_plan_id' => $lessonPlan->id,
            'section_id' => Section::orderBy('id')->firstOrFail()->id,
            'date' => $date,
        ]);
    }

    /**
     * スタッフとして、指定した日付の出席記録・一覧画面を開く。
     */
    private function getAttendances(string $date): TestResponse
    {
        return $this->actingAs($this->staff, 'staff')
            ->get(route('admin.attendances.index', ['date' => $date]));
    }

    /**
     * 指定した曜日（省略時は土曜日）の授業実施単位（A1／A2／A3／B）に、指定した授業クラスの授業回を1件作る。
     * lessonsの(lesson_plan_id, section_id)の複合UNIQUE制約に当たらないよう、
     * その授業実施単位でまだ使っていない授業内容を選ぶ。
     */
    private function createLesson(string $date, string $sectionName, string $classCode, string $weekday = '土'): Lesson
    {
        $section = Section::where('name', $sectionName)->where('weekday', $weekday)->firstOrFail();

        $lessonPlan = LessonPlan::whereHas('schoolClass', fn ($query) => $query->where('code', $classCode))
            ->whereDoesntHave('lessons', fn ($query) => $query->where('section_id', $section->id))
            ->orderBy('no')
            ->firstOrFail();

        return Lesson::create([
            'lesson_plan_id' => $lessonPlan->id,
            'section_id' => $section->id,
            'date' => $date,
        ]);
    }

    /**
     * 指定した曜日（省略時は土曜日）の授業実施単位に所属する生徒を1人作る。
     * 並び順の確認で分かりやすいよう、名前と名前カナには同じ文字列を入れる。
     */
    private function createStudent(
        string $sectionName,
        string $classCode,
        string $grade,
        string $name,
        ?string $leaveFrom = null,
        ?string $leaveUntil = null,
        string $weekday = '土',
    ): Student {
        return Student::create([
            'guardian_id' => Guardian::factory()->create()->id,
            'school_class_id' => SchoolClass::where('code', $classCode)->firstOrFail()->id,
            'section_id' => Section::where('name', $sectionName)->where('weekday', $weekday)->firstOrFail()->id,
            'grade' => $grade,
            'name' => $name,
            'name_kana' => $name,
            'leave_from' => $leaveFrom,
            'leave_until' => $leaveUntil,
        ]);
    }

    /**
     * 欠席して、別の授業回に振替・30分前補講で来る予定の欠席記録を作る。
     */
    private function createMakeup(
        Student $student,
        Lesson $absentLesson,
        Lesson $makeupLesson,
        string $makeupType,
        ?string $makeupStatus = null,
    ): Attendance {
        return Attendance::create([
            'student_id' => $student->id,
            'lesson_id' => $absentLesson->id,
            'status' => '欠席',
            'is_late' => false,
            'makeup_type' => $makeupType,
            'makeup_lesson_id' => $makeupLesson->id,
            'makeup_status' => $makeupStatus,
        ]);
    }

    /**
     * 通常の授業回の出席記録を作る。statusがnullなら未記録（遅刻の連絡だけ来ている状態など）。
     */
    private function createAttendance(Student $student, Lesson $lesson, ?string $status, bool $isLate = false): Attendance
    {
        return Attendance::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => $status,
            'is_late' => $isLate,
        ]);
    }
}
