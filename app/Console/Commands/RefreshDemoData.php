<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationEvent;
use App\Models\ApplicationMessage;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Updates the existing demo students and their applications in place with
 * realistic names/content, without creating, deleting or migrating anything.
 *
 * Never touches su92-bssem-f22-171@superior.edu.pk or the seeded Super
 * Admin / Admin Officer accounts — only the demo students created by
 * DemoSeeder and their own applications.
 */
class RefreshDemoData extends Command
{
    protected $signature = 'demo:refresh';

    /**
     * @var array<int, string>
     */
    private const PROTECTED_EMAILS = [
        'su92-bssem-f22-171@superior.edu.pk',
    ];

    public function handle(): void
    {
        $protectedEmails = array_merge(
            self::PROTECTED_EMAILS,
            [config('seeding.super_admin.email'), config('seeding.admin_officer.email')],
        );

        $demoStudents = Student::query()
            ->whereHas('user', fn ($query) => $query->whereNotIn('email', $protectedEmails))
            ->with('user')
            ->orderBy('id')
            ->get();

        $definitions = DemoSeeder::demoStudents();

        if ($demoStudents->count() !== count($definitions)) {
            throw new RuntimeException(sprintf(
                'Expected %d demo students but found %d. Refusing to guess — update the command or the dataset to match.',
                count($definitions),
                $demoStudents->count(),
            ));
        }

        $departmentId = $demoStudents->first()->user->department_id;
        $categories = ApplicationCategory::query()->where('department_id', $departmentId)->get()->keyBy('name');
        $adminUserId = User::query()->where('department_id', $departmentId)
            ->role(RoleName::AdminOfficer->value)->value('id');

        $globalIndex = 0;
        $applicationCount = 0;

        foreach ($demoStudents->values() as $index => $student) {
            $def = $definitions[$index];
            $registrationNo = DemoSeeder::registrationNo($def);

            $student->user->update([
                'name' => $def['name'],
                'email' => strtolower($registrationNo).config('students.email_domain'),
            ]);

            $student->update([
                'registration_no' => $registrationNo,
                'program' => config('students.programs')[$def['program_code']],
                'current_semester' => $def['current_semester'],
                'batch' => $def['batch'],
            ]);

            $applications = $student->applications()->orderBy('id')->get();

            if ($applications->count() !== count($def['applications'])) {
                throw new RuntimeException(sprintf(
                    'Student "%s" (id %d) has %d applications but the dataset expects %d. Refusing to guess.',
                    $def['name'],
                    $student->id,
                    $applications->count(),
                    count($def['applications']),
                ));
            }

            foreach ($applications->values() as $appIndex => $application) {
                $appDef = $def['applications'][$appIndex];
                $attributes = DemoSeeder::applicationAttributes($appDef, $globalIndex, $student->current_semester);

                /** @var Application $application */
                $application->update(array_merge(
                    ['category_id' => $categories[$appDef['category']]->id],
                    $attributes,
                ));

                // created_at/updated_at aren't mass-assignable.
                $application->forceFill([
                    'created_at' => $attributes['created_at'],
                    'updated_at' => $attributes['updated_at'],
                ])->save();

                ApplicationEvent::query()->where('application_id', $application->id)->delete();
                DemoSeeder::createEvents($application, $appDef, $globalIndex, $student->user_id, $adminUserId);

                ApplicationMessage::query()->where('application_id', $application->id)->delete();
                if (! empty($appDef['messages'])) {
                    DemoSeeder::createMessages($application, $appDef['messages'], $globalIndex, $student->user_id, $adminUserId);
                }

                $globalIndex++;
                $applicationCount++;
            }
        }

        $this->info(sprintf('Refreshed %d demo students and %d applications.', $demoStudents->count(), $applicationCount));
    }
}
