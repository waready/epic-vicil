<?php

namespace Database\Seeders;

use App\Models\AccreditationCriterion;
use App\Models\AccreditationModel;
use App\Models\AccreditationSubcriterion;
use App\Models\EvidenceRequirement;
use Illuminate\Database\Seeder;

class AccreditationModelSeeder extends Seeder
{
    public function run(): void
    {
        $icacit = AccreditationModel::updateOrCreate(
            ['code' => 'ICACIT'],
            [
                'name' => 'ICACIT',
                'level' => 'internacional',
                'description' => 'Modelo flexible para acreditacion internacional de programas profesionales.',
                'is_active' => true,
            ]
        );

        $sineace = AccreditationModel::updateOrCreate(
            ['code' => 'SINEACE'],
            [
                'name' => 'SINEACE',
                'level' => 'nacional',
                'description' => 'Modelo nacional peruano de evaluacion y acreditacion de calidad educativa.',
                'is_active' => true,
            ]
        );

        $this->seedIcacit($icacit);
        $this->seedSineace($sineace);
    }

    private function seedIcacit(AccreditationModel $model): void
    {
        $criteria = [
            ['C1', 'Estudiantes', [
                ['C1-DN-01', 'Reglamentos academicos y normativos estudiantiles', 'program', 'normative'],
                ['C1-REG-01', 'Registros de admision, convalidacion, practicas y graduacion', 'program', 'record'],
                ['C1-REG-02', 'Evidencias de consejeria y seguimiento estudiantil', 'program', 'record'],
            ]],
            ['C2', 'Objetivos Educacionales', [
                ['C2-DN-01', 'Objetivos educacionales aprobados y publicados', 'program', 'normative'],
                ['C2-REG-01', 'Participacion de constituyentes en revision de objetivos', 'program', 'record'],
                ['C2-REG-02', 'Base de egresados y seguimiento de empleabilidad', 'program', 'record'],
            ]],
            ['C3', 'Atributos del Graduado', [
                ['C3-DN-01', 'Atributos del graduado aprobados', 'program', 'normative'],
                ['C3-REG-01', 'Rubricas e instrumentos de medicion', 'program', 'record'],
                ['C3-REG-02', 'Resultados de medicion de atributos del graduado', 'program', 'record'],
                ['C3-ASS-01', 'Trabajos de todos los estudiantes del curso de medicion', 'assessment_course', 'assessment'],
                ['C3-ASS-02', 'Guias de assessment del curso de medicion', 'assessment_course', 'assessment'],
                ['C3-ASS-03', 'Rubricas de assessment del resultado del estudiante', 'assessment_course', 'assessment'],
                ['C3-ASS-04', 'Video de demostracion o sustentacion de 10 minutos', 'assessment_course', 'video', ['mp4']],
            ]],
            ['C4', 'Mejora Continua', [
                ['C4-DN-01', 'Sistema de aseguramiento de calidad y mejora continua', 'program', 'normative'],
                ['C4-REG-01', 'Resultados de medicion directa e indirecta', 'program', 'record'],
                ['C4-REG-02', 'Planes de mejora, seguimiento e impacto', 'improvement_plan', 'record'],
            ]],
            ['C5', 'Plan de Estudios', [
                ['C5-DN-01', 'Plan de estudios vigente y matriz curricular', 'program', 'normative'],
                ['C5-PORT-01', 'Silabo del curso, temario, sesiones de aprendizaje y cronograma de avance', 'course_offering', 'portfolio'],
                ['C5-PORT-03', 'Examenes aplicados, formatos, solucionarios y criterios de calificacion', 'course_offering', 'portfolio'],
                ['C5-PORT-04', 'Guias de trabajo, practicas, laboratorios y trabajos encargados', 'course_offering', 'portfolio'],
                ['C5-PORT-05', 'Trabajos de estudiantes clasificados: bueno, regular y malo', 'course_offering', 'portfolio'],
                ['C5-PORT-06', 'Rubricas, actas de notas y resultados de aprendizaje del curso', 'course_offering', 'portfolio'],
                ['C5-PORT-07', 'Material de ensenanza del curso', 'course_offering', 'portfolio'],
                ['C5-PROJ-01', 'Proyectos integradores o experiencia de diseno', 'integrator_project', 'record'],
            ]],
            ['C6', 'Cuerpo de Profesores', [
                ['C6-DN-01', 'Politicas de contratacion, evaluacion y desarrollo docente', 'program', 'normative'],
                ['C6-REG-01', 'CV docente y documentos de soporte', 'teacher', 'record'],
                ['C6-REG-02', 'Carga docente, evaluacion y capacitacion', 'teacher', 'record'],
            ]],
            ['C7', 'Instalaciones', [
                ['C7-DN-01', 'Politicas de mantenimiento e infraestructura', 'program', 'normative'],
                ['C7-REG-01', 'Laboratorios, equipos, software y licencias', 'laboratory', 'record'],
                ['C7-REG-02', 'Mantenimientos preventivos y correctivos', 'facility', 'record'],
            ]],
            ['C8', 'Apoyo Institucional', [
                ['C8-DN-01', 'Presupuesto, fondos y apoyo institucional', 'program', 'normative'],
                ['C8-REG-01', 'Capacitacion, movilidad y desarrollo profesional', 'program', 'record'],
                ['C8-REG-02', 'Inclusion, responsabilidad social y apoyo al programa', 'program', 'record'],
            ]],
            ['C9', 'Criterios del Programa', [
                ['C9-REG-01', 'Evidencias especificas segun disciplina profesional', 'program', 'record'],
            ]],
            ['C10', 'Investigacion y Responsabilidad Social', [
                ['C10-REG-01', 'Investigacion, innovacion, emprendimiento y responsabilidad social', 'program', 'record'],
            ]],
            ['C11', 'Sello Internacional', [
                ['C11-REG-01', 'Evidencias asociadas al sello internacional cuando aplique', 'program', 'record'],
            ]],
        ];

        foreach ($criteria as $order => [$code, $name, $requirements]) {
            $criterion = AccreditationCriterion::updateOrCreate(
                ['accreditation_model_id' => $model->id, 'code' => $code],
                ['name' => $name, 'order' => $order + 1, 'is_active' => true]
            );

            $subcriterion = AccreditationSubcriterion::updateOrCreate(
                ['accreditation_criterion_id' => $criterion->id, 'code' => $code.'.1'],
                ['name' => 'Gestion documental del criterio', 'order' => 1, 'is_active' => true]
            );

            foreach ($requirements as $reqOrder => $requirementData) {
                [$reqCode, $reqName, $appliesTo, $kind] = array_slice($requirementData, 0, 4);
                $allowedExtensions = $requirementData[4] ?? config('accreditation.allowed_extensions');

                EvidenceRequirement::updateOrCreate(
                    ['accreditation_criterion_id' => $criterion->id, 'code' => $reqCode],
                    [
                        'accreditation_subcriterion_id' => $subcriterion->id,
                        'name' => $reqName,
                        'applies_to' => $appliesTo,
                        'evidence_kind' => $kind,
                        'is_required' => true,
                        'allows_multiple_files' => true,
                        'allowed_extensions' => $allowedExtensions,
                        'order' => $reqOrder + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedSineace(AccreditationModel $model): void
    {
        $criteria = [
            ['F1', 'Gestion estrategica'],
            ['F2', 'Formacion integral'],
            ['F3', 'Soporte institucional'],
            ['F4', 'Resultados'],
        ];

        foreach ($criteria as $order => [$code, $name]) {
            $criterion = AccreditationCriterion::updateOrCreate(
                ['accreditation_model_id' => $model->id, 'code' => $code],
                ['name' => $name, 'order' => $order + 1, 'is_active' => true]
            );

            AccreditationSubcriterion::updateOrCreate(
                ['accreditation_criterion_id' => $criterion->id, 'code' => $code.'.1'],
                ['name' => 'Estandar inicial configurable', 'order' => 1, 'is_active' => true]
            );
        }
    }
}
