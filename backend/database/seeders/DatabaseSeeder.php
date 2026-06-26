<?php

namespace Database\Seeders;

use App\Models\AcademicProgram;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\AccreditationCycle;
use App\Models\AccreditationModel;
use App\Models\CourseAssignment;
use App\Models\CourseOffering;
use App\Models\CurriculumCourse;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceTask;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\StudyPlan;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AccreditationModelSeeder::class,
        ]);

        $users = $this->seedUsers();
        $institution = $this->seedInstitution();
        $program = $this->seedAcademicCatalog($institution, $users['docente']);
        $this->seedCourseOfferingsFromExcel($institution, $program);
        $this->seedAssessmentPlan($institution, $program);
        $this->cleanupDemoCatalogData();
        $this->cleanupLegacyTeacherNameTypos();
        $this->normalizeLegacyTeacherUsers();
        $this->normalizeDuplicateTeachers();
        $this->cleanupInvalidSeedData();
        $cycles = $this->seedAccreditationCycles($program);
        $cycle = $cycles[0];
        $this->seedEvidenceTasks($cycle, $users);
        $this->cleanupInvalidSeedData();
    }

    private function seedUsers(): array
    {
        $items = [
            'admin' => ['Administrador del Sistema', 'admin@acreditacion.local', 'super_admin'],
            'director' => ['Director de Escuela Profesional', 'director@acreditacion.local', 'director_programa'],
            'coordinador' => ['Coordinador de Acreditacion', 'coordinador@acreditacion.local', 'coordinador_acreditacion'],
            'docente' => ['Docente Responsable', 'docente@acreditacion.local', 'docente'],
            'consulta' => ['Usuario de Consulta', 'consulta@acreditacion.local', 'consulta'],
        ];

        $users = [];

        foreach ($items as $key => [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->is_active = true;

            if (! $user->exists) {
                $user->password = Hash::make('password');
                $user->must_change_password = true;
                $user->password_changed_at = null;
            }

            $user->save();
            $user->syncRoles([$role]);
            $users[$key] = $user;
        }

        return $users;
    }

    private function seedInstitution(): Institution
    {
        return Institution::updateOrCreate(
            ['short_name' => 'UNAP'],
            [
                'name' => 'Universidad Nacional del Altiplano Puno',
                'ruc' => '20145496170',
                'website' => 'https://portal.unap.edu.pe',
                'is_active' => true,
            ]
        );
    }

    private function seedAcademicCatalog(Institution $institution, User $docenteUser): AcademicProgram
    {
        $faculty = Faculty::updateOrCreate(
            ['institution_id' => $institution->id, 'code' => 'FICA'],
            ['name' => 'Facultad de Ingenieria Civil y Arquitectura', 'is_active' => true]
        );

        $program = AcademicProgram::updateOrCreate(
            ['faculty_id' => $faculty->id, 'code' => 'EPIC'],
            [
                'name' => 'Escuela Profesional de Ingenieria Civil',
                'degree_name' => 'Bachiller en Ingenieria Civil',
                'professional_title' => 'Ingeniero Civil',
                'modality' => 'presencial',
                'is_active' => true,
            ]
        );

        $year = AcademicYear::updateOrCreate(
            ['institution_id' => $institution->id, 'year' => 2026],
            ['name' => 'Anio Academico 2026', 'is_active' => true]
        );

        $term = AcademicTerm::updateOrCreate(
            ['academic_year_id' => $year->id, 'code' => '2026-I'],
            ['name' => '2026-I', 'starts_on' => '2026-03-01', 'ends_on' => '2026-07-31', 'is_active' => true]
        );

        AcademicTerm::updateOrCreate(
            ['academic_year_id' => $year->id, 'code' => '2026-II'],
            ['name' => '2026-II', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-31', 'is_active' => true]
        );

        $studyPlan = StudyPlan::updateOrCreate(
            ['program_id' => $program->id, 'code' => 'IC-2026'],
            ['name' => 'Plan de Estudios Ingenieria Civil 2026', 'year' => 2026, 'is_current' => true, 'is_active' => true]
        );

        $legacyPlan = StudyPlan::updateOrCreate(
            ['program_id' => $program->id, 'code' => 'IC-2018'],
            ['name' => 'Plan de Estudios Ingenieria Civil 2018', 'year' => 2018, 'is_current' => false, 'is_active' => true]
        );

        $teacher = Teacher::updateOrCreate(
            ['institution_id' => $institution->id, 'document_type' => 'DNI', 'document_number' => '00000001'],
            [
                'user_id' => $docenteUser->id,
                'first_name' => 'Docente',
                'last_name' => 'Responsable',
                'email' => $docenteUser->email,
                'highest_degree' => 'Magister',
                'specialty' => 'Estructuras',
                'employment_type' => 'ordinario',
                'is_active' => true,
            ]
        );

        return $program;
    }

    private function cleanupDemoCatalogData(): void
    {
        $demoCourseCodes = [
            'IC501',
            'IC418',
            'ASM-I01',
            'ASM-I02',
            'ASM-I03',
            'ASM-I04',
            'ASM-I06',
            'ASM-I07',
            'ASM-I10',
            'ASM-I11',
            'ASM-I12',
        ];
        $courses = CurriculumCourse::withTrashed()
            ->whereIn('code', $demoCourseCodes)
            ->get();

        if ($courses->isEmpty()) {
            return;
        }

        $offeringIds = CourseOffering::withTrashed()
            ->whereIn('course_id', $courses->pluck('id'))
            ->pluck('id');

        if ($offeringIds->isNotEmpty()) {
            EvidenceTask::withTrashed()
                ->whereIn('context_type', ['course_offering', 'assessment_course'])
                ->whereIn('context_id', $offeringIds)
                ->forceDelete();

            CourseAssignment::query()
                ->whereIn('course_offering_id', $offeringIds)
                ->delete();

            CourseOffering::withTrashed()
                ->whereIn('id', $offeringIds)
                ->forceDelete();
        }

        foreach ($courses as $course) {
            if (! CourseOffering::withTrashed()->where('course_id', $course->id)->exists()) {
                $course->forceDelete();
            }
        }
    }

    private function cleanupLegacyTeacherNameTypos(): void
    {
        $aliases = [
            'aaconcori@docentes.com' => 'aacondori@docentes.com',
        ];

        foreach ($aliases as $wrongEmail => $rightEmail) {
            $wrongUser = User::withTrashed()->where('email', $wrongEmail)->first();
            $rightUser = User::withTrashed()->where('email', $rightEmail)->first();
            $wrongTeacher = Teacher::withTrashed()->where('email', $wrongEmail)->first();
            $rightTeacher = Teacher::withTrashed()->where('email', $rightEmail)->first();

            if (! $wrongUser && ! $wrongTeacher) {
                continue;
            }

            if ($wrongTeacher && $rightTeacher) {
                CourseAssignment::query()
                    ->where('teacher_id', $wrongTeacher->id)
                    ->get()
                    ->each(function (CourseAssignment $assignment) use ($rightTeacher) {
                        $exists = CourseAssignment::query()
                            ->where('course_offering_id', $assignment->course_offering_id)
                            ->where('teacher_id', $rightTeacher->id)
                            ->where('role', $assignment->role)
                            ->exists();

                        if ($exists) {
                            $assignment->delete();
                            return;
                        }

                        $assignment->update(['teacher_id' => $rightTeacher->id]);
                    });

                DB::table('evidence_submissions')
                    ->where('teacher_id', $wrongTeacher->id)
                    ->update(['teacher_id' => $rightTeacher->id]);

                EvidenceTask::query()
                    ->where('context_type', 'teacher')
                    ->where('context_id', $wrongTeacher->id)
                    ->delete();

                $wrongTeacher->delete();
            }

            if ($wrongUser && $rightUser) {
                EvidenceTask::query()
                    ->where('assigned_to', $wrongUser->id)
                    ->update(['assigned_to' => $rightUser->id]);

                DB::table('evidence_submissions')
                    ->where('submitted_by', $wrongUser->id)
                    ->update(['submitted_by' => $rightUser->id]);

                $wrongUser->tokens()->delete();
                $wrongUser->delete();
            }
        }
    }

    private function seedAssessmentPlan(Institution $institution, AcademicProgram $program): void
    {
        $term = AcademicTerm::query()->where('code', '2026-I')->firstOrFail();
        $studyPlan = StudyPlan::query()
            ->where('program_id', $program->id)
            ->where('code', 'IC-2026')
            ->firstOrFail();

        $assessmentRows = [
            [
                'result_code' => 'RE-I01',
                'result_name' => 'Conocimientos de Ingenieria',
                'course_code' => 'CIV316',
                'course_name' => 'Ingenieria de Cimentaciones',
                'teachers' => ['Mariano Roberto Garcia Loayza', 'Fausto Ponciano Mamani Mamani'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I02',
                'result_name' => 'Analisis de Problemas',
                'course_code' => 'CIV310',
                'course_name' => 'Puentes y obras de arte',
                'teachers' => ['Marwin Douglas Mendoza Larico', 'Alexis Anibal Condori Colque'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I03',
                'result_name' => 'Diseno o Desarrollo de Soluciones',
                'course_code' => 'CIV330',
                'course_name' => 'Taller en proyectos de transporte',
                'teachers' => ['Walter Hugo Lipa Condori', 'Gleny Zoila De La Riva Tapia', 'Helmer Reynaldo Vilca Apaza'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I04',
                'result_name' => 'Indagacion',
                'course_code' => 'CIV335',
                'course_name' => 'Trabajo de investigacion',
                'teachers' => ['Douglas Arturo Quintanilla Anyaipoma', 'Gleny Zoila De La Riva Tapia', 'Gino Nels Najar Vizcarra'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I05',
                'result_name' => 'Uso de Herramientas Modernas',
                'course_code' => 'CIV311',
                'course_name' => 'Ingenieria sismorresistente',
                'teachers' => ['Marwin Douglas Mendoza Larico', 'Alex Darwin Roque Roque'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I06',
                'result_name' => 'Ingenieria y Sociedad',
                'course_code' => 'CIV305',
                'course_name' => 'Legislacion en la construccion',
                'teachers' => ['Gino Nels Najar Vizcarra', 'Alexis Anibal Condori Colque'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I07',
                'result_name' => 'Medio Ambiente y Sostenibilidad',
                'course_code' => 'CIV337',
                'course_name' => 'Gestion ambiental',
                'teachers' => ['Zenon Mellado Vargas', 'Nancy Zevallos Quispe'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I08',
                'result_name' => 'Etica',
                'course_code' => 'CIV334',
                'course_name' => 'Metodologia de la Investigacion',
                'teachers' => ['Douglas Arturo Quintanilla Anyaipoma', 'Nestor Leodan Suca Suca'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I09',
                'result_name' => 'Trabajo Individual y en Equipo',
                'course_code' => 'CIV304',
                'course_name' => 'Taller de proyectos de edificacion',
                'teachers' => ['Diana Elizabeth Quinto Gastiaburu', 'Jaime Medina Leiva', 'Emilio Augusto Molina Chavez'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I10',
                'result_name' => 'Comunicacion',
                'course_code' => 'CIV338',
                'course_name' => 'Practica pre profesional',
                'teachers' => ['Carlos Alberto Gonzales Gutierrez'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I11',
                'result_name' => 'Gestion de proyectos',
                'course_code' => 'CIV303',
                'course_name' => 'Gestion y administracion en la construccion',
                'teachers' => ['Carlos Alberto Gonzales Gutierrez', 'Gino Nels Najar Vizcarra'],
                'requires_video' => true,
            ],
            [
                'result_code' => 'RE-I12',
                'result_name' => 'Aprendizaje permanente',
                'course_code' => 'CIV335',
                'course_name' => 'Trabajo de investigacion',
                'teachers' => ['Douglas Arturo Quintanilla Anyaipoma', 'Gleny Zoila De La Riva Tapia', 'Gino Nels Najar Vizcarra'],
                'requires_video' => true,
            ],
        ];

        $assessmentOfferingIds = [];

        foreach ($assessmentRows as $row) {
            $course = CurriculumCourse::firstOrNew(['study_plan_id' => $studyPlan->id, 'code' => $row['course_code']]);

            if (! $course->exists) {
                $course->fill([
                    'name' => $row['course_name'],
                    'cycle_number' => 9,
                    'credits' => 4,
                    'theory_hours' => 2,
                    'practice_hours' => 2,
                    'lab_hours' => 0,
                    'is_required' => true,
                    'is_active' => true,
                ]);
            } else {
                $course->is_active = true;
            }

            $course->save();

            $offering = CourseOffering::updateOrCreate(
                [
                    'program_id' => $program->id,
                    'academic_term_id' => $term->id,
                    'course_id' => $course->id,
                    'section' => 'ASSESSMENT-'.$row['result_code'],
                ],
                [
                    'group_code' => $row['result_code'],
                    'enrolled_count' => 0,
                    'status' => 'active',
                    'is_assessment_course' => true,
                    'assessment_result_code' => $row['result_code'],
                    'assessment_result_name' => $row['result_name'],
                    'requires_assessment_video' => $row['requires_video'],
                ]
            );
            $assessmentOfferingIds[] = $offering->id;

            $teacherIds = [];
            foreach ($row['teachers'] as $index => $teacherName) {
                $teacher = $this->seedTeacherFromName($institution, $teacherName);
                $teacherIds[] = $teacher->id;
                CourseAssignment::updateOrCreate(
                    [
                        'course_offering_id' => $offering->id,
                        'teacher_id' => $teacher->id,
                        'role' => $index === 0 ? 'main' : 'co_teacher',
                    ],
                    ['weekly_hours' => null]
                );
            }

            CourseAssignment::query()
                ->where('course_offering_id', $offering->id)
                ->whereNotIn('teacher_id', $teacherIds)
                ->delete();
        }

        $this->pruneSeededAssessmentOfferings($program, $term, $assessmentOfferingIds);
    }

    private function pruneSeededAssessmentOfferings(AcademicProgram $program, AcademicTerm $term, array $validOfferingIds): void
    {
        $obsolete = CourseOffering::query()
            ->where('program_id', $program->id)
            ->where('academic_term_id', $term->id)
            ->where('section', 'like', 'ASSESSMENT%')
            ->where('is_assessment_course', true)
            ->when($validOfferingIds !== [], fn ($query) => $query->whereNotIn('id', $validOfferingIds))
            ->get();

        if ($obsolete->isEmpty()) {
            return;
        }

        EvidenceTask::query()
            ->where('context_type', 'assessment_course')
            ->whereIn('context_id', $obsolete->pluck('id'))
            ->delete();

        foreach ($obsolete as $offering) {
            $offering->delete();
        }
    }

    private function seedCourseOfferingsFromExcel(Institution $institution, AcademicProgram $program): void
    {
        $path = base_path('../imports/Horario con carga academica EPIC 2026-I.xlsx');

        if (! is_file($path) || ! class_exists(\ZipArchive::class)) {
            return;
        }

        $term = AcademicTerm::query()->where('code', '2026-I')->firstOrFail();
        $studyPlan = StudyPlan::query()
            ->where('program_id', $program->id)
            ->where('code', 'IC-2026')
            ->firstOrFail();

        foreach ($this->readCargaHorariaRows($path) as $row) {
            $course = CurriculumCourse::updateOrCreate(
                ['study_plan_id' => $studyPlan->id, 'code' => $row['course_code']],
                [
                    'name' => $row['course_name'],
                    'cycle_number' => $this->romanCycleToNumber($row['cycle']),
                    'credits' => max((int) ($row['total_hours'] ?: 0), 0),
                    'theory_hours' => max((int) ($row['theory_hours'] ?: 0), 0),
                    'practice_hours' => max((int) ($row['practice_hours'] ?: 0), 0),
                    'lab_hours' => 0,
                    'is_required' => true,
                    'is_active' => true,
                ]
            );

            $offering = CourseOffering::firstOrNew([
                'program_id' => $program->id,
                'academic_term_id' => $term->id,
                'course_id' => $course->id,
                'section' => $row['section'] ?: 'U',
            ]);

            $offering->fill([
                'group_code' => $row['course_code'].'-'.($row['section'] ?: 'U'),
                'enrolled_count' => $offering->enrolled_count ?: 0,
                'status' => 'active',
            ]);

            if (! $offering->exists) {
                $offering->fill([
                    'is_assessment_course' => false,
                    'assessment_result_code' => null,
                    'assessment_result_name' => null,
                    'requires_assessment_video' => false,
                ]);
            }

            $offering->save();

            if (! $row['teacher_name']) {
                continue;
            }

            $teacher = $this->seedTeacherFromName($institution, $row['teacher_name']);
            $mainAssignment = CourseAssignment::query()
                ->where('course_offering_id', $offering->id)
                ->where('role', 'main')
                ->first();
            $role = (! $mainAssignment || (int) $mainAssignment->teacher_id === (int) $teacher->id) ? 'main' : 'co_teacher';

            CourseAssignment::updateOrCreate(
                ['course_offering_id' => $offering->id, 'teacher_id' => $teacher->id, 'role' => $role],
                ['weekly_hours' => $row['total_hours'] ?: null]
            );
        }
    }

    private function readCargaHorariaRows(string $path): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

            if (! $sheetXml) {
                return [];
            }

            $document = new \DOMDocument();
            $document->loadXML($sheetXml);
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $rows = [];
            foreach ($xpath->query('//x:sheetData/x:row') as $rowNode) {
                $rowNumber = (int) $rowNode->getAttribute('r');
                if ($rowNumber <= 3) {
                    continue;
                }

                $cells = [];
                foreach ($xpath->query('x:c', $rowNode) as $cellNode) {
                    $column = preg_replace('/\d+/', '', $cellNode->getAttribute('r'));
                    $cells[$column] = $this->xlsxCellValue($xpath, $cellNode, $sharedStrings);
                }

                if (empty($cells['A']) || empty($cells['C'])) {
                    continue;
                }

                $rows[] = [
                    'course_code' => trim($cells['A']),
                    'course_name' => trim($cells['C']),
                    'cycle' => trim($cells['D'] ?? ''),
                    'section' => trim($cells['F'] ?? ''),
                    'theory_hours' => trim($cells['I'] ?? ''),
                    'practice_hours' => trim($cells['J'] ?? ''),
                    'total_hours' => trim($cells['K'] ?? ''),
                    'teacher_name' => $this->teacherNameFromExcel($cells['L'] ?? null),
                ];
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        $document = new \DOMDocument();
        $document->loadXML($xml);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($xpath->query('//x:si') as $node) {
            $parts = [];
            foreach ($xpath->query('.//x:t', $node) as $textNode) {
                $parts[] = $textNode->textContent;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function xlsxCellValue(\DOMXPath $xpath, \DOMElement $cellNode, array $sharedStrings): string
    {
        $type = $cellNode->getAttribute('t');

        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($xpath->query('.//x:t', $cellNode) as $textNode) {
                $parts[] = $textNode->textContent;
            }

            return trim(implode('', $parts));
        }

        $valueNode = $xpath->query('x:v', $cellNode)->item(0);
        $value = $valueNode ? $valueNode->textContent : '';

        if ($type === 's' && $value !== '') {
            return trim($sharedStrings[(int) $value] ?? '');
        }

        return trim($value);
    }

    private function teacherNameFromExcel(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            [$lastNames, $firstNames] = array_map('trim', explode(',', $value, 2));
            return trim($this->titleCaseName($firstNames).' '.$this->titleCaseName($lastNames));
        }

        return $this->titleCaseName($value);
    }

    private function titleCaseName(string $value): string
    {
        return trim(mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
    }

    private function romanCycleToNumber(?string $value): ?int
    {
        return [
            'I' => 1,
            'II' => 2,
            'III' => 3,
            'IV' => 4,
            'V' => 5,
            'VI' => 6,
            'VII' => 7,
            'VIII' => 8,
            'IX' => 9,
            'X' => 10,
        ][strtoupper(trim((string) $value))] ?? null;
    }

    private function seedTeacherFromName(Institution $institution, string $fullName): Teacher
    {
        $name = trim(preg_replace('/\s+/', ' ', $fullName));
        $parts = explode(' ', $name);
        if (count($parts) > 2) {
            $lastName = implode(' ', array_slice($parts, -2));
            $firstName = implode(' ', array_slice($parts, 0, -2));
        } else {
            $lastName = array_pop($parts);
            $firstName = count($parts) > 0 ? implode(' ', $parts) : $name;
        }
        $email = $this->shortTeacherEmail($name);
        $legacyEmail = Str::slug(Str::ascii($name), '.').'@docentes.unap.local';

        $user = User::query()
            ->where('email', $email)
            ->orWhere('email', $legacyEmail)
            ->first();

        if (! $user) {
            $user = new User();
        }

        $isNewUser = ! $user->exists;
        $user->name = $name;
        $user->email = $email;
        $user->is_active = true;

        if ($isNewUser) {
            $user->password = Hash::make('password');
            $user->must_change_password = true;
            $user->password_changed_at = null;
        }

        $user->save();
        $user->syncRoles(['docente']);

        $teacher = Teacher::withTrashed()
            ->where('user_id', $user->id)
            ->orWhere('email', $email)
            ->first();

        if (! $teacher) {
            $teacher = new Teacher([
                'institution_id' => $institution->id,
                'document_type' => 'AUTO',
                'document_number' => substr(sha1($name), 0, 12),
            ]);
        }

        $teacher->fill([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'highest_degree' => $teacher->highest_degree ?: 'Por registrar',
            'specialty' => $teacher->specialty ?: 'Ingenieria Civil',
            'employment_type' => $teacher->employment_type ?: 'docente',
            'is_active' => true,
        ]);

        if ($teacher->trashed()) {
            $teacher->restore();
        }

        $teacher->save();

        return $teacher;
    }

    private function normalizeLegacyTeacherUsers(): void
    {
        $legacyUsers = User::query()
            ->where('email', 'like', '%@docentes.unap.local')
            ->get();

        foreach ($legacyUsers as $legacyUser) {
            $shortEmail = $this->shortTeacherEmail($legacyUser->name);
            $target = User::query()
                ->where('email', $shortEmail)
                ->where('id', '!=', $legacyUser->id)
                ->first();

            if (! $target) {
                $legacyUser->update(['email' => $shortEmail, 'is_active' => true]);
                $legacyUser->syncRoles(['docente']);
                Teacher::query()
                    ->where('user_id', $legacyUser->id)
                    ->update(['email' => $shortEmail]);

                continue;
            }

            Teacher::query()
                ->where('user_id', $legacyUser->id)
                ->update([
                    'user_id' => $target->id,
                    'email' => $target->email,
                ]);

            EvidenceTask::query()
                ->where('assigned_to', $legacyUser->id)
                ->update(['assigned_to' => $target->id]);

            $legacyUser->delete();
        }
    }

    private function normalizeDuplicateTeachers(): void
    {
        $duplicateEmails = Teacher::query()
            ->select('email')
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        foreach ($duplicateEmails as $email) {
            $teachers = Teacher::query()
                ->where('email', $email)
                ->orderBy('id')
                ->get();
            $keeper = $teachers->first();

            if (! $keeper) {
                continue;
            }

            foreach ($teachers->skip(1) as $duplicate) {
                CourseAssignment::query()
                    ->where('teacher_id', $duplicate->id)
                    ->get()
                    ->each(function (CourseAssignment $assignment) use ($keeper) {
                        $exists = CourseAssignment::query()
                            ->where('course_offering_id', $assignment->course_offering_id)
                            ->where('teacher_id', $keeper->id)
                            ->where('role', $assignment->role)
                            ->exists();

                        if ($exists) {
                            $assignment->delete();
                            return;
                        }

                        $assignment->update(['teacher_id' => $keeper->id]);
                    });

                DB::table('teacher_degrees')
                    ->where('teacher_id', $duplicate->id)
                    ->update(['teacher_id' => $keeper->id]);

                DB::table('teacher_trainings')
                    ->where('teacher_id', $duplicate->id)
                    ->update(['teacher_id' => $keeper->id]);

                DB::table('evidence_submissions')
                    ->where('teacher_id', $duplicate->id)
                    ->update(['teacher_id' => $keeper->id]);

                $duplicate->forceDelete();
            }
        }
    }

    private function cleanupInvalidSeedData(): void
    {
        CourseAssignment::query()
            ->whereDoesntHave('courseOffering')
            ->delete();

        EvidenceTask::query()
            ->whereIn('context_type', ['course_offering', 'assessment_course'])
            ->whereDoesntHave('courseOfferingContext')
            ->delete();

        $assessmentOfferingIds = CourseOffering::query()
            ->where('is_assessment_course', true)
            ->pluck('id');

        if ($assessmentOfferingIds->isEmpty()) {
            return;
        }

        EvidenceTask::query()
            ->where('context_type', 'course_offering')
            ->whereIn('context_id', $assessmentOfferingIds)
            ->delete();
    }

    private function shortTeacherEmail(string $name): string
    {
        $tokens = preg_split('/\s+/', trim(Str::lower(Str::ascii($name)))) ?: [];
        $tokens = array_values(array_filter($tokens));

        if (count($tokens) < 2) {
            return (Str::slug($name, '') ?: 'docente').'@docentes.com';
        }

        if (count($tokens) === 2) {
            $givenNames = [$tokens[0]];
            $firstSurname = $tokens[1];
        } else {
            $maternalIndex = count($tokens) - 1;
            $surnameStart = $maternalIndex - 1;
            $particles = ['de', 'del', 'la', 'las', 'los', 'san', 'santa'];

            while ($surnameStart > 0 && in_array($tokens[$surnameStart - 1], $particles, true)) {
                $surnameStart--;
            }

            $givenNames = array_slice($tokens, 0, $surnameStart);
            $firstSurname = implode('', array_slice($tokens, $surnameStart, $maternalIndex - $surnameStart));
        }

        $initials = collect($givenNames)
            ->filter()
            ->map(fn (string $part) => mb_substr($part, 0, 1, 'UTF-8'))
            ->implode('');

        return ($initials.$firstSurname).'@docentes.com';
    }

    private function seedAccreditationCycles(AcademicProgram $program): array
    {
        $model = AccreditationModel::where('code', 'ICACIT')->firstOrFail();
        $terms = AcademicTerm::query()
            ->with('year')
            ->whereIn('code', ['2026-I', '2026-II'])
            ->orderBy('code')
            ->get()
            ->keyBy('code');
        $termOne = $terms->get('2026-I');
        $termTwo = $terms->get('2026-II');

        AccreditationCycle::query()
            ->where('accreditation_model_id', $model->id)
            ->where('program_id', $program->id)
            ->where('name', 'ICACIT 2026 Ingenieria Civil')
            ->update(['status' => 'archived']);

        return [
            AccreditationCycle::updateOrCreate(
                ['accreditation_model_id' => $model->id, 'program_id' => $program->id, 'academic_term_id' => $termOne?->id, 'name' => 'ICACIT 2026-I Ingenieria Civil'],
                [
                    'year' => 2026,
                    'starts_on' => $termOne?->starts_on ?? '2026-03-01',
                    'ends_on' => $termOne?->ends_on ?? '2026-07-31',
                    'status' => 'active',
                    'settings' => ['export_valid_statuses' => ['validated', 'approved', 'ready_to_export']],
                ]
            ),
            AccreditationCycle::updateOrCreate(
                ['accreditation_model_id' => $model->id, 'program_id' => $program->id, 'academic_term_id' => $termTwo?->id, 'name' => 'ICACIT 2026-II Ingenieria Civil'],
                [
                    'year' => 2026,
                    'starts_on' => $termTwo?->starts_on ?? '2026-08-01',
                    'ends_on' => $termTwo?->ends_on ?? '2026-12-31',
                    'status' => 'planning',
                    'settings' => ['export_valid_statuses' => ['validated', 'approved', 'ready_to_export']],
                ]
            ),
        ];
    }

    private function seedEvidenceTasks(AccreditationCycle $cycle, array $users): void
    {
        $requirements = EvidenceRequirement::query()
            ->with('criterion')
            ->whereHas('criterion', fn ($query) => $query->where('accreditation_model_id', $cycle->accreditation_model_id))
            ->where('is_active', true)
            ->get();

        $teacher = Teacher::where('user_id', $users['docente']->id)->first();
        $courseOfferings = CourseOffering::query()
            ->with(['mainAssignment.teacher.user', 'assignments.teacher.user'])
            ->where('program_id', $cycle->program_id)
            ->when($cycle->academic_term_id, fn ($query) => $query->where('academic_term_id', $cycle->academic_term_id))
            ->get();
        $programTeacherIds = $courseOfferings
            ->flatMap(fn (CourseOffering $offering) => $offering->assignments->pluck('teacher_id'))
            ->filter()
            ->unique()
            ->values();
        $teachers = Teacher::query()
            ->with('user')
            ->whereIn('id', $programTeacherIds)
            ->get();

        if ($teachers->isEmpty() && $teacher) {
            $teachers = collect([$teacher->loadMissing('user')]);
        }

        foreach ($requirements as $requirement) {
            $contextType = $requirement->applies_to;
            $contextId = $cycle->program_id;
            $assignedTo = $users['coordinador']->id;
            $extra = [];

            if ($contextType === 'teacher') {
                foreach ($teachers as $teacherItem) {
                    $this->upsertEvidenceTask(
                        [
                            'accreditation_cycle_id' => $cycle->id,
                            'evidence_requirement_id' => $requirement->id,
                            'context_type' => $contextType,
                            'context_id' => $teacherItem->id,
                        ],
                        [
                            'program_id' => $cycle->program_id,
                            'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                            'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                            'assigned_to' => $teacherItem->user_id,
                            'status' => 'pending',
                            'priority' => $requirement->is_required ? 'high' : 'normal',
                            'instructions' => 'Evidencia docente asociada al criterio '.$requirement->criterion->code.'.',
                        ]
                    );
                }

                continue;
            }

            if ($contextType === 'course_offering') {
                foreach ($courseOfferings->where('is_assessment_course', false) as $courseOffering) {
                    $mainAssignment = $courseOffering->mainAssignment;

                    $this->upsertEvidenceTask(
                        [
                            'accreditation_cycle_id' => $cycle->id,
                            'evidence_requirement_id' => $requirement->id,
                            'context_type' => $contextType,
                            'context_id' => $courseOffering->id,
                        ],
                        [
                            'program_id' => $cycle->program_id,
                            'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                            'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                            'academic_term_id' => $courseOffering->academic_term_id,
                            'assigned_to' => $mainAssignment?->teacher?->user_id,
                            'status' => 'pending',
                            'priority' => $requirement->is_required ? 'high' : 'normal',
                            'instructions' => 'Portafolio de curso: '.$requirement->name.'.',
                        ]
                    );
                }

                continue;
            }

            if ($contextType === 'assessment_course') {
                foreach ($courseOfferings->where('is_assessment_course', true) as $courseOffering) {
                    if ($requirement->code === 'C3-ASS-04' && ! $courseOffering->requires_assessment_video) {
                        continue;
                    }

                    $mainAssignment = $courseOffering->mainAssignment;
                    $label = trim(($courseOffering->assessment_result_code ?: 'Assessment').' '.$courseOffering->assessment_result_name);

                    $this->upsertEvidenceTask(
                        [
                            'accreditation_cycle_id' => $cycle->id,
                            'evidence_requirement_id' => $requirement->id,
                            'context_type' => $contextType,
                            'context_id' => $courseOffering->id,
                        ],
                        [
                            'program_id' => $cycle->program_id,
                            'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                            'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                            'academic_term_id' => $courseOffering->academic_term_id,
                            'assigned_to' => $mainAssignment?->teacher?->user_id,
                            'status' => 'pending',
                            'priority' => $requirement->code === 'C3-ASS-04' ? 'high' : ($requirement->is_required ? 'high' : 'normal'),
                            'instructions' => 'Assessment '.$label.': '.$requirement->name.'.',
                            'metadata' => [
                                'assessment_result_code' => $courseOffering->assessment_result_code,
                                'assessment_result_name' => $courseOffering->assessment_result_name,
                                'requires_video' => $courseOffering->requires_assessment_video,
                            ],
                        ]
                    );
                }

                continue;
            }

            $this->upsertEvidenceTask(
                [
                    'accreditation_cycle_id' => $cycle->id,
                    'evidence_requirement_id' => $requirement->id,
                    'context_type' => $contextType,
                    'context_id' => $contextId,
                ],
                array_merge([
                    'program_id' => $cycle->program_id,
                    'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                    'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                    'assigned_to' => $assignedTo,
                    'status' => 'pending',
                    'priority' => $requirement->is_required ? 'high' : 'normal',
                ], $extra)
            );
        }
    }

    private function upsertEvidenceTask(array $attributes, array $values): void
    {
        $task = EvidenceTask::firstOrNew($attributes);

        if ($task->exists) {
            unset($values['status']);
        }

        $task->fill($values);
        $task->save();
    }
}
