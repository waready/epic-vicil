<template>
  <q-page padding class="app-page">
    <div class="page-header">
      <div>
        <div class="page-kicker">Registro documental</div>
        <div class="page-title">Subir evidencia</div>
        <div class="page-subtitle">Registra archivos con metadatos de acreditacion, curso y responsable.</div>
      </div>
      <q-btn flat icon="arrow_back" label="Volver" to="/evidences" />
    </div>

    <q-card flat bordered class="form-panel">
      <q-card-section>
        <q-form @submit.prevent="submit">
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-4">
              <q-select v-model="form.program_id" :options="programOptions" label="Programa" outlined dense emit-value map-options @update:model-value="loadTasks" />
            </div>
            <div class="col-12 col-md-4">
              <q-select v-model="form.accreditation_cycle_id" :options="cycleOptions" label="Ciclo" outlined dense emit-value map-options @update:model-value="loadTasks" />
            </div>
            <div class="col-12 col-md-4">
              <q-select v-model="form.criterion_id" :options="criterionOptions" label="Criterio" outlined dense emit-value map-options @update:model-value="onCriterionChange" />
            </div>
            <div class="col-12 col-md-4">
              <q-select v-model="form.subcriterion_id" :options="subcriterionOptions" label="Subcriterio" outlined dense emit-value map-options clearable @update:model-value="loadTasks" />
            </div>
            <div class="col-12 col-md-8">
              <q-select v-model="form.evidence_requirement_id" :options="requirementOptions" label="Requerimiento" outlined dense emit-value map-options @update:model-value="onRequirementChange" />
            </div>
            <div class="col-12 col-md-6">
              <q-select v-model="form.evidence_task_id" :options="taskOptions" label="Tarea asociada" outlined dense clearable emit-value map-options @update:model-value="applyTask" />
            </div>
            <div class="col-12 col-md-6">
              <q-select v-model="form.study_plan_id" :options="studyPlanOptions" label="Curricula / plan" outlined dense clearable emit-value map-options />
            </div>
            <div class="col-12 col-md-6">
              <q-select v-model="form.course_id" :options="courseOptions" label="Curso" outlined dense clearable emit-value map-options />
            </div>
            <div class="col-12 col-md-6">
              <q-select v-model="form.teacher_id" :options="teacherOptions" label="Docente" outlined dense clearable emit-value map-options />
            </div>
            <div class="col-12">
              <q-input v-model="form.title" label="Titulo" outlined dense />
            </div>
            <div class="col-12">
              <q-input v-model="form.description" label="Descripcion" type="textarea" outlined />
            </div>
            <div class="col-12">
              <q-file v-model="file" label="Archivo" outlined clearable counter :disable="loading" />
              <div v-if="directUploading" class="q-mt-md">
                <div class="row items-center justify-between q-mb-xs">
                  <span class="text-caption text-grey-7">Subiendo directamente al almacenamiento externo</span>
                  <span class="text-caption text-weight-bold">{{ uploadProgress }}%</span>
                </div>
                <q-linear-progress :value="uploadProgress / 100" color="primary" rounded />
              </div>
            </div>
          </div>

          <div class="q-mt-lg">
            <q-btn color="primary" icon="cloud_upload" label="Subir" type="submit" :loading="loading" />
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script>
import { canFallbackToServer, uploadDirectFile } from 'src/utils/directUpload'

export default {
  name: 'EvidenceCreatePage',

  data () {
    return {
      loading: false,
      directUploading: false,
      uploadProgress: 0,
      programs: [],
      cycles: [],
      criteria: [],
      studyPlans: [],
      requirements: [],
      tasks: [],
      courses: [],
      teachers: [],
      file: null,
      form: {
        program_id: null,
        accreditation_cycle_id: null,
        criterion_id: null,
        subcriterion_id: null,
        evidence_requirement_id: null,
        evidence_task_id: null,
        study_plan_id: null,
        course_id: null,
        teacher_id: null,
        title: '',
        description: ''
      }
    }
  },

  computed: {
    programOptions () {
      return this.programs.map(item => ({ label: item.name, value: item.id }))
    },
    cycleOptions () {
      return this.cycles.map(item => ({ label: item.name, value: item.id }))
    },
    criterionOptions () {
      return this.criteria.map(item => ({ label: `${item.code} - ${item.name}`, value: item.id }))
    },
    selectedCriterion () {
      return this.criteria.find(item => item.id === this.form.criterion_id)
    },
    subcriterionOptions () {
      const subcriteria = this.selectedCriterion && this.selectedCriterion.subcriteria ? this.selectedCriterion.subcriteria : []
      return subcriteria.map(item => ({ label: `${item.code || ''} - ${item.name}`.trim(), value: item.id }))
    },
    requirementOptions () {
      return this.requirements.map(item => ({
        label: `${item.code} - ${item.name}${item.applies_to === 'teacher' ? ' / Docente' : ''}`,
        value: item.id
      }))
    },
    studyPlanOptions () {
      return this.studyPlans.map(item => ({ label: `${item.code || 'Plan'} - ${item.name}`, value: item.id }))
    },
    taskOptions () {
      return this.tasks.map(item => ({
        label: `${item.requirement ? item.requirement.code : ''} - ${item.requirement ? item.requirement.name : 'Tarea'}${this.taskContextLabel(item)} (${item.status})`,
        value: item.id
      }))
    },
    courseOptions () {
      return this.courses
        .filter(item => !this.form.study_plan_id || item.study_plan_id === this.form.study_plan_id)
        .map(item => ({ label: `${item.code} - ${item.name}`, value: item.id }))
    },
    teacherOptions () {
      return this.teachers.map(item => ({ label: `${item.last_name}, ${item.first_name}`, value: item.id }))
    }
  },

  created () {
    this.loadCatalogs()
  },

  methods: {
    async loadCatalogs () {
      try {
        const [programs, cycles, criteria, studyPlans, courses, teachers] = await Promise.all([
          this.$api.get('/programs'),
          this.$api.get('/accreditation-cycles'),
          this.$api.get('/accreditation-criteria'),
          this.$api.get('/study-plans'),
          this.$api.get('/courses'),
          this.$api.get('/teachers')
        ])
        this.programs = programs.data
        this.cycles = cycles.data
        this.criteria = criteria.data
        this.studyPlans = studyPlans.data
        this.courses = courses.data
        this.teachers = teachers.data

        if (this.programs.length) this.form.program_id = this.programs[0].id
        if (this.cycles.length) this.form.accreditation_cycle_id = this.cycles[0].id
        if (this.studyPlans.length) this.form.study_plan_id = this.studyPlans[0].id
        if (this.criteria.length) {
          this.form.criterion_id = this.criteria[0].id
          await this.onCriterionChange(this.form.criterion_id)
        }
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los catalogos' })
      }
    },

    async onCriterionChange (criterionId) {
      this.form.evidence_requirement_id = null
      this.form.subcriterion_id = null
      await this.loadRequirements(criterionId)
      await this.loadTasks()
    },

    async onRequirementChange (requirementId) {
      const requirement = this.requirements.find(item => item.id === requirementId)
      if (requirement) {
        this.form.subcriterion_id = requirement.accreditation_subcriterion_id || null
        this.form.title = this.form.title || requirement.name
      }
      await this.loadTasks()
    },

    async loadRequirements (criterionId) {
      if (!criterionId) {
        this.requirements = []
        return
      }
      const response = await this.$api.get('/evidence-requirements', { params: { criterion_id: criterionId } })
      this.requirements = response.data
      if (this.requirements.length) {
        this.form.evidence_requirement_id = this.requirements[0].id
        await this.onRequirementChange(this.form.evidence_requirement_id)
      }
    },

    async loadTasks () {
      if (!this.form.program_id || !this.form.accreditation_cycle_id || !this.form.criterion_id) return
      const params = {
        program_id: this.form.program_id,
        accreditation_cycle_id: this.form.accreditation_cycle_id,
        criterion_id: this.form.criterion_id
      }
      if (this.form.evidence_requirement_id) params.evidence_requirement_id = this.form.evidence_requirement_id
      if (this.form.subcriterion_id) params.subcriterion_id = this.form.subcriterion_id
      const response = await this.$api.get('/evidence-tasks/catalog', { params })
      this.tasks = response.data
    },

    applyTask (taskId) {
      const task = this.tasks.find(item => item.id === taskId)
      if (!task) return
      const offering = task.course_offering || task.course_offering_context || null

      this.form.program_id = task.program ? task.program.id : this.form.program_id
      this.form.accreditation_cycle_id = task.cycle ? task.cycle.id : this.form.accreditation_cycle_id
      this.form.criterion_id = task.criterion ? task.criterion.id : this.form.criterion_id
      this.form.subcriterion_id = task.subcriterion ? task.subcriterion.id : null
      this.form.evidence_requirement_id = task.requirement ? task.requirement.id : this.form.evidence_requirement_id
      this.form.study_plan_id = offering && offering.course ? offering.course.study_plan_id : this.form.study_plan_id
      this.form.course_id = offering ? offering.course_id : this.form.course_id
      this.form.title = task.requirement ? task.requirement.name : this.form.title
    },

    taskContextLabel (task) {
      const offering = task.course_offering || task.course_offering_context
      if (!offering) return ''

      const course = offering.course ? `${offering.course.code} ${offering.course.name}` : 'Curso'
      const assessment = offering.assessment_result_code
        ? ` / ${offering.assessment_result_code}${offering.assessment_result_name ? ' - ' + offering.assessment_result_name : ''}`
        : ''

      return ` / ${course}${assessment}`
    },

    async submit () {
      if (!this.file) {
        this.$q.notify({ type: 'warning', message: 'Selecciona un archivo' })
        return
      }

      this.loading = true
      this.uploadProgress = 0
      try {
        let asset = null

        try {
          asset = await this.uploadEvidenceDirect(this.file)
        } catch (directError) {
          if (!canFallbackToServer([this.file], directError)) {
            throw directError
          }
        }

        const response = asset
          ? await this.$api.post('/evidences', {
              ...this.form,
              file_asset_id: asset.id
            })
          : await this.submitThroughServer()

        this.$q.notify({ type: 'positive', message: 'Evidencia subida correctamente' })
        this.$router.push(`/evidences/${response.data.data.id}`)
      } catch (error) {
        const message = error.response?.data?.message || 'No se pudo subir la evidencia'
        this.$q.notify({ type: 'negative', message })
      } finally {
        this.loading = false
        this.directUploading = false
      }
    },

    async uploadEvidenceDirect (file) {
      this.directUploading = true
      const context = this.form.evidence_task_id
        ? { evidence_task_id: this.form.evidence_task_id }
        : {
            program_id: this.form.program_id,
            accreditation_cycle_id: this.form.accreditation_cycle_id,
            criterion_id: this.form.criterion_id,
            course_id: this.form.course_id,
            teacher_id: this.form.teacher_id
          }

      return uploadDirectFile({
        api: this.$api,
        http: this.$axios,
        file,
        context,
        onProgress: progress => {
          this.uploadProgress = progress
        }
      })
    },

    submitThroughServer () {
      this.directUploading = false
        const data = new FormData()
        Object.keys(this.form).forEach(key => {
          if (this.form[key] !== null && this.form[key] !== '') data.append(key, this.form[key])
        })
        data.append('file', this.file)

      return this.$api.post('/evidences', data, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
    }
  }
}
</script>
