<?php

namespace App\Actions\Timetable;

use App\Enums\TimetableVersionStatus;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Support\Timetable\ParsedTimetable;
use App\Support\Timetable\SectionExpander;
use App\Support\Timetable\TimetableParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Throwable;

/**
 * Parses an uploaded timetable workbook and stores it as a new draft
 * version of the department's timetable, ready for the admin to preview
 * and publish. The original file is kept on the private disk.
 */
class ImportTimetable
{
    public function __construct(private TimetableParser $parser) {}

    /**
     * @throws Exception when the file is not a readable workbook
     */
    public function handle(UploadedFile $file, int $departmentId, User $uploader): TimetableVersion
    {
        $parsed = $this->parser->parse((string) $file->getRealPath());

        $path = (string) $file->store('timetables/'.$departmentId, 'local');

        try {
            return $this->storeVersion($parsed, $path, $file->getClientOriginalName(), $departmentId, $uploader);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    private function storeVersion(ParsedTimetable $parsed, string $path, string $originalName, int $departmentId, User $uploader): TimetableVersion
    {
        return DB::transaction(function () use ($parsed, $path, $originalName, $departmentId, $uploader) {
            $versionNo = (int) TimetableVersion::withoutGlobalScopes()
                ->where('department_id', $departmentId)
                ->lockForUpdate()
                ->max('version_no') + 1;

            $version = TimetableVersion::create([
                'department_id' => $departmentId,
                'version_no' => $versionNo,
                'uploaded_by' => $uploader->id,
                'original_name' => $originalName,
                'file_path' => $path,
                'status' => TimetableVersionStatus::Draft,
                'class_count' => count($parsed->classes),
                'unparsed_cells' => $parsed->unparsedCells,
            ]);

            foreach ($parsed->classes as $class) {
                $timetableClass = $version->classes()->create([
                    'day' => $class->day,
                    'starts_at' => $class->startsAt,
                    'ends_at' => $class->endsAt,
                    'room' => $class->room,
                    'floor' => $class->floor,
                    'course' => $class->course,
                    'type' => $class->type,
                    'teacher' => $class->teacher,
                    'raw_sections' => $class->rawSections,
                    'sheet' => $class->sheet,
                    'cell' => $class->cell,
                ]);

                $timetableClass->sections()->createMany(array_map(
                    fn (string $section): array => ['section' => $section, ...SectionExpander::describe($section)],
                    $class->sections,
                ));
            }

            return $version;
        });
    }
}
