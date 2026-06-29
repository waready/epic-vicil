<template>
  <q-page padding class="app-page teacher-tracking-page">
    <div class="page-header">
      <div>
        <div class="page-kicker">Control academico</div>
        <div class="page-title">Seguimiento de evidencias por docente</div>
        <div class="page-subtitle">Consulta responsabilidades, documentos enviados y pendientes por curso.</div>
      </div>
      <q-btn color="primary" icon="refresh" label="Actualizar" unelevated :loading="loadingTeachers" @click="refreshAll" />
    </div>

    <section class="tracking-filters">
      <div class="row q-col-gutter-md items-end">
        <div class="col-12 col-md-3">
          <q-select
            v-model="filters.cycle_id"
            :options="cycleOptions"
            label="Ciclo de acreditacion"
            outlined
            dense
            emit-value
            map-options
            clearable
          />
        </div>
        <div class="col-12 col-md-3">
          <q-select
            v-model="filters.program_id"
            :options="programOptions"
            label="Programa"
            outlined
            dense
            emit-value
            map-options
            clearable
          />
        </div>
        <div class="col-12 col-md-4">
          <q-input v-model="filters.search" label="Buscar docente o correo" outlined dense clearable @keyup.enter="applyFilters">
            <template v-slot:prepend><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-12 col-md-2">
          <q-btn class="full-width" color="primary" icon="filter_alt" label="Aplicar" unelevated @click="applyFilters" />
        </div>
      </div>
    </section>

    <q-card flat bordered class="data-panel q-mt-lg">
      <q-table
        v-model:pagination="teacherPagination"
        :rows="teachers"
        :columns="teacherColumns"
        row-key="user_id"
        flat
        :loading="loadingTeachers"
        :rows-per-page-options="[10, 15, 25, 50]"
        @request="onTeacherRequest"
      >
        <template v-slot:top-left>
          <div>
            <div class="panel-title">Docentes responsables</div>
            <div class="text-caption text-grey-7">Ordenados por cantidad de evidencias faltantes.</div>
          </div>
        </template>

        <template v-slot:body-cell-name="props">
          <q-td :props="props">
            <div class="teacher-identity">
              <q-avatar color="blue-1" text-color="primary" size="38px">{{ initials(props.row.name) }}</q-avatar>
              <div>
                <div class="text-weight-bold">{{ props.row.name }}</div>
                <div class="text-caption text-grey-7">{{ props.row.email }}</div>
              </div>
            </div>
          </q-td>
        </template>

        <template v-slot:body-cell-missing="props">
          <q-td :props="props">
            <q-badge :color="props.row.missing > 0 ? 'orange-8' : 'positive'" class="status-count">
              {{ props.row.missing }}
            </q-badge>
          </q-td>
        </template>

        <template v-slot:body-cell-progress="props">
          <q-td :props="props">
            <div class="progress-cell">
              <q-linear-progress :value="props.row.progress / 100" size="8px" rounded color="primary" track-color="blue-1" />
              <span>{{ props.row.progress }}%</span>
            </div>
          </q-td>
        </template>

        <template v-slot:body-cell-last_submission_at="props">
          <q-td :props="props">{{ formatDate(props.row.last_submission_at) }}</q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props">
            <q-btn round flat color="primary" icon="manage_search" @click="selectTeacher(props.row)">
              <q-tooltip>Ver evidencias asignadas</q-tooltip>
            </q-btn>
          </q-td>
        </template>

        <template v-slot:no-data>
          <div class="full-width row flex-center q-pa-xl text-grey-7">
            <q-icon name="person_search" size="28px" class="q-mr-sm" />
            No se encontraron docentes con evidencias asignadas.
          </div>
        </template>
      </q-table>
    </q-card>

    <q-dialog
      v-model="teacherDetailDialog"
      maximized
      transition-show="slide-up"
      transition-hide="slide-down"
      @hide="closeTeacherDetail"
    >
      <q-card v-if="selectedTeacher" square class="teacher-detail-dialog">
        <q-toolbar class="teacher-detail-toolbar">
          <div class="teacher-identity">
            <q-avatar color="primary" text-color="white" size="46px">{{ initials(selectedTeacher.name) }}</q-avatar>
            <div>
              <div class="panel-title">Evidencias asignadas: {{ selectedTeacher.name }}</div>
              <div class="text-body2 text-grey-7">{{ selectedTeacher.email }}</div>
            </div>
          </div>
          <q-space />
          <q-btn flat round icon="close" color="grey-7" v-close-popup>
            <q-tooltip>Cerrar detalle</q-tooltip>
          </q-btn>
        </q-toolbar>
        <q-separator />

        <q-card-section class="teacher-detail-content">
          <div class="tracking-metrics">
        <div class="tracking-metric">
          <q-icon name="assignment" color="primary" />
          <div><strong>{{ selectedTeacher.total }}</strong><span>Asignadas</span></div>
        </div>
        <div class="tracking-metric">
          <q-icon name="cloud_done" color="indigo-7" />
          <div><strong>{{ selectedTeacher.submitted }}</strong><span>Enviadas</span></div>
        </div>
        <div class="tracking-metric">
          <q-icon name="schedule" color="orange-8" />
          <div><strong>{{ selectedTeacher.missing }}</strong><span>Faltantes</span></div>
        </div>
        <div class="tracking-metric">
          <q-icon name="task_alt" color="positive" />
          <div><strong>{{ selectedTeacher.accepted }}</strong><span>Validadas</span></div>
        </div>
          </div>

          <div class="detail-toolbar">
        <q-btn-toggle
          v-model="detailFilters.scope"
          class="detail-scope-toggle"
          no-caps
          unelevated
          toggle-color="primary"
          color="white"
          text-color="grey-8"
          :options="scopeOptions"
          @update:model-value="applyDetailFilters"
        />
        <q-input v-model="detailFilters.search" dense outlined clearable placeholder="Buscar curso o requerimiento" @keyup.enter="applyDetailFilters">
          <template v-slot:prepend><q-icon name="search" /></template>
        </q-input>
          </div>

          <q-card flat bordered class="data-panel">
        <q-table
          v-model:pagination="taskPagination"
          :rows="tasks"
          :columns="taskColumns"
          row-key="id"
          flat
          :loading="loadingTasks"
          :rows-per-page-options="[10, 15, 25, 50]"
          @request="onTaskRequest"
        >
          <template v-slot:body-cell-context="props">
            <q-td :props="props">
              <div class="context-label">{{ props.row.context_label || 'Evidencia institucional' }}</div>
              <div class="text-caption text-grey-7">{{ cycleLabel(props.row.cycle) }}</div>
            </q-td>
          </template>

          <template v-slot:body-cell-requirement="props">
            <q-td :props="props">
              <div class="requirement-code">{{ props.row.requirement?.code || 'Sin codigo' }}</div>
              <div class="requirement-name">{{ props.row.requirement?.name || 'Sin requerimiento' }}</div>
              <div class="text-caption text-grey-7">{{ criterionLabel(props.row) }}</div>
            </q-td>
          </template>

          <template v-slot:body-cell-status="props">
            <q-td :props="props">
              <q-chip dense :color="statusColor(props.row.status)" text-color="white" :icon="statusIcon(props.row.status)">
                {{ statusLabel(props.row.status) }}
              </q-chip>
            </q-td>
          </template>

          <template v-slot:body-cell-submission="props">
            <q-td :props="props">
              <button v-if="latestSubmission(props.row)" type="button" class="file-summary" @click="openSubmissions(props.row)">
                <q-icon :name="fileIcon(latestSubmission(props.row).current_file)" color="primary" size="22px" />
                <span>
                  <strong>{{ submissionFileName(latestSubmission(props.row)) }}</strong>
                  <small>{{ submissionCaption(props.row) }}</small>
                </span>
              </button>
              <div v-else class="empty-submission"><q-icon name="cloud_off" /> Sin envio</div>
            </q-td>
          </template>

          <template v-slot:body-cell-actions="props">
            <q-td :props="props" class="q-gutter-xs">
              <q-btn
                v-if="latestSubmission(props.row)"
                flat
                round
                color="primary"
                icon="visibility"
                @click="openEvidence(latestSubmission(props.row))"
              >
                <q-tooltip>Ver detalle de la evidencia</q-tooltip>
              </q-btn>
              <q-btn
                v-if="latestSubmission(props.row)?.current_file?.can_preview"
                flat
                round
                color="secondary"
                icon="open_in_new"
                @click="previewFile(latestSubmission(props.row).current_file)"
              >
                <q-tooltip>Previsualizar archivo</q-tooltip>
              </q-btn>
            </q-td>
          </template>

          <template v-slot:no-data>
            <div class="full-width row flex-center q-pa-xl text-grey-7">
              <q-icon name="folder_off" size="28px" class="q-mr-sm" />
              No hay evidencias para este filtro.
            </div>
          </template>
        </q-table>
          </q-card>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="submissionsDialog">
      <q-card class="submissions-dialog">
        <q-card-section class="row items-start justify-between">
          <div>
            <div class="text-h6">Archivos enviados</div>
            <div class="text-body2 text-grey-7">{{ selectedTask?.requirement?.name }}</div>
          </div>
          <q-btn flat round icon="close" v-close-popup />
        </q-card-section>
        <q-separator />
        <q-list separator>
          <q-item v-for="submission in selectedTask?.submissions || []" :key="submission.id">
            <q-item-section avatar>
              <q-icon :name="fileIcon(submission.current_file)" color="primary" />
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ submissionFileName(submission) }}</q-item-label>
              <q-item-label caption>{{ submission.title }} - V{{ submission.version_number }} - {{ formatDate(submission.submitted_at) }}</q-item-label>
            </q-item-section>
            <q-item-section side>
              <div class="q-gutter-xs">
                <q-btn flat round icon="visibility" color="primary" @click="openEvidence(submission)" />
                <q-btn v-if="submission.current_file?.can_preview" flat round icon="open_in_new" color="secondary" @click="previewFile(submission.current_file)" />
              </div>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card>
    </q-dialog>

  </q-page>
</template>

<script>
export default {
  name: 'TeacherEvidenceTrackingPage',

  data () {
    return {
      loadingTeachers: false,
      loadingTasks: false,
      teachers: [],
      tasks: [],
      cycles: [],
      programs: [],
      selectedTeacher: null,
      selectedTask: null,
      submissionsDialog: false,
      teacherDetailDialog: false,
      filters: { cycle_id: null, program_id: null, search: '' },
      detailFilters: { scope: 'all', search: '' },
      teacherPagination: { page: 1, rowsPerPage: 15, rowsNumber: 0, sortBy: null, descending: false },
      taskPagination: { page: 1, rowsPerPage: 15, rowsNumber: 0, sortBy: null, descending: false },
      scopeOptions: [
        { label: 'Todas', value: 'all', icon: 'view_list' },
        { label: 'Faltantes', value: 'missing', icon: 'schedule' },
        { label: 'Enviadas', value: 'submitted', icon: 'cloud_done' },
        { label: 'Observadas', value: 'observed', icon: 'rate_review' },
        { label: 'Validadas', value: 'accepted', icon: 'task_alt' }
      ],
      teacherColumns: [
        { name: 'name', label: 'Docente', field: 'name', align: 'left' },
        { name: 'total', label: 'Asignadas', field: 'total', align: 'center' },
        { name: 'submitted', label: 'Enviadas', field: 'submitted', align: 'center' },
        { name: 'missing', label: 'Faltantes', field: 'missing', align: 'center' },
        { name: 'observed', label: 'Observadas', field: 'observed', align: 'center' },
        { name: 'accepted', label: 'Validadas', field: 'accepted', align: 'center' },
        { name: 'progress', label: 'Avance', field: 'progress', align: 'left' },
        { name: 'last_submission_at', label: 'Ultimo envio', field: 'last_submission_at', align: 'left' },
        { name: 'actions', label: '', align: 'center' }
      ],
      taskColumns: [
        { name: 'context', label: 'Curso / contexto', field: 'context_label', align: 'left' },
        { name: 'requirement', label: 'Evidencia requerida', field: row => row.requirement?.name, align: 'left' },
        { name: 'status', label: 'Estado', field: 'status', align: 'center' },
        { name: 'submission', label: 'Que subio', field: row => this.submissionFileName(this.latestSubmission(row)), align: 'left' },
        { name: 'actions', label: 'Acciones', align: 'center' }
      ]
    }
  },

  computed: {
    cycleOptions () {
      return this.cycles.map(cycle => ({ label: this.cycleLabel(cycle), value: cycle.id }))
    },

    programOptions () {
      return this.programs.map(program => ({ label: `${program.code} - ${program.name}`, value: program.id }))
    }
  },

  async created () {
    await this.loadOptions()
    await this.loadTeachers()
  },

  methods: {
    async loadOptions () {
      try {
        const [cycles, programs] = await Promise.all([
          this.$api.get('/accreditation-cycles'),
          this.$api.get('/programs')
        ])
        this.cycles = cycles.data || []
        this.programs = programs.data || []
        const activeCycle = this.cycles.find(cycle => cycle.status === 'active')
        if (activeCycle) this.filters.cycle_id = activeCycle.id
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los filtros' })
      }
    },

    async loadTeachers () {
      this.loadingTeachers = true
      try {
        const response = await this.$api.get('/admin/teacher-evidence-tracking', {
          params: {
            ...this.filters,
            page: this.teacherPagination.page,
            per_page: this.teacherPagination.rowsPerPage
          }
        })
        this.teachers = response.data.data || []
        this.teacherPagination.rowsNumber = response.data.total || 0

        if (this.selectedTeacher) {
          const updated = this.teachers.find(item => item.user_id === this.selectedTeacher.user_id)
          if (updated) this.selectedTeacher = updated
        }
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo cargar el seguimiento docente' })
      } finally {
        this.loadingTeachers = false
      }
    },

    async loadTasks () {
      if (!this.selectedTeacher) return
      this.loadingTasks = true
      try {
        const response = await this.$api.get(`/admin/teacher-evidence-tracking/${this.selectedTeacher.user_id}/tasks`, {
          params: {
            cycle_id: this.filters.cycle_id,
            program_id: this.filters.program_id,
            scope: this.detailFilters.scope,
            search: this.detailFilters.search,
            page: this.taskPagination.page,
            per_page: this.taskPagination.rowsPerPage
          }
        })
        this.tasks = response.data.data || []
        this.taskPagination.rowsNumber = response.data.meta?.total || response.data.total || 0
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo cargar el detalle del docente' })
      } finally {
        this.loadingTasks = false
      }
    },

    applyFilters () {
      this.teacherPagination.page = 1
      this.closeTeacherDetail()
      this.loadTeachers()
    },

    applyDetailFilters () {
      this.taskPagination.page = 1
      this.loadTasks()
    },

    refreshAll () {
      this.loadTeachers()
      if (this.selectedTeacher) this.loadTasks()
    },

    onTeacherRequest ({ pagination }) {
      this.teacherPagination = { ...this.teacherPagination, ...pagination }
      this.loadTeachers()
    },

    onTaskRequest ({ pagination }) {
      this.taskPagination = { ...this.taskPagination, ...pagination }
      this.loadTasks()
    },

    selectTeacher (teacher) {
      this.selectedTeacher = teacher
      this.teacherDetailDialog = true
      this.detailFilters = { scope: 'all', search: '' }
      this.taskPagination.page = 1
      this.loadTasks()
    },

    closeTeacherDetail () {
      this.teacherDetailDialog = false
      this.selectedTeacher = null
      this.tasks = []
    },

    openSubmissions (task) {
      this.selectedTask = task
      this.submissionsDialog = true
    },

    openEvidence (submission) {
      if (!submission?.id) return
      this.submissionsDialog = false
      this.$router.push(`/evidences/${submission.id}`)
    },

    previewFile (file) {
      const url = file?.preview_url || file?.url
      if (url) window.open(url, '_blank', 'noopener,noreferrer')
    },

    latestSubmission (task) {
      return task?.current_submission || task?.submissions?.[0] || null
    },

    submissionFileName (submission) {
      return submission?.current_file?.original_name || submission?.title || 'Envio registrado'
    },

    submissionCaption (task) {
      const submissions = task?.submissions || []
      const latest = this.latestSubmission(task)
      const count = submissions.length
      return `${count} envio${count === 1 ? '' : 's'} - ${this.formatDate(latest?.submitted_at)}`
    },

    fileIcon (file) {
      return {
        pdf: 'picture_as_pdf',
        image: 'image',
        video: 'smart_display',
        document: 'description',
        spreadsheet: 'table_view',
        presentation: 'slideshow',
        archive: 'folder_zip'
      }[file?.file_type] || 'draft'
    },

    initials (name) {
      return String(name || 'D').split(' ').filter(Boolean).slice(0, 2).map(item => item[0]).join('').toUpperCase()
    },

    cycleLabel (cycle) {
      if (!cycle) return 'Sin ciclo'
      const model = cycle.model?.code || ''
      const term = cycle.term?.code || ''
      return [model, term].filter(Boolean).join(' ') || cycle.name
    },

    criterionLabel (task) {
      const criterion = task?.criterion
      const subcriterion = task?.subcriterion
      return [criterion ? `${criterion.code} - ${criterion.name}` : '', subcriterion?.code || ''].filter(Boolean).join(' / ')
    },

    formatDate (value) {
      if (!value) return 'Sin envio'
      const date = new Date(value)
      if (Number.isNaN(date.getTime())) return 'Sin envio'
      return new Intl.DateTimeFormat('es-PE', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date)
    },

    statusLabel (status) {
      return {
        pending: 'Pendiente', assigned: 'Asignada', uploaded: 'Subida', in_review: 'En revision',
        observed: 'Observada', corrected: 'Corregida', validated: 'Validada', approved: 'Aprobada',
        ready_to_export: 'Lista para exportar'
      }[status] || status
    },

    statusColor (status) {
      return {
        pending: 'orange-8', assigned: 'blue-grey-6', uploaded: 'indigo-7', in_review: 'blue-7',
        observed: 'negative', corrected: 'deep-orange-7', validated: 'positive', approved: 'teal-7',
        ready_to_export: 'primary'
      }[status] || 'grey-7'
    },

    statusIcon (status) {
      return {
        pending: 'schedule', assigned: 'assignment_ind', uploaded: 'cloud_done', in_review: 'manage_search',
        observed: 'rate_review', corrected: 'published_with_changes', validated: 'task_alt', approved: 'verified',
        ready_to_export: 'inventory_2'
      }[status] || 'info'
    }
  }
}
</script>

<style scoped>
.tracking-filters {
  padding: 18px;
  border: 1px solid #d9e2ec;
  border-radius: 8px;
  background: #fff;
}

.panel-title {
  color: #102a43;
  font-size: 18px;
  font-weight: 700;
}

.teacher-identity {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 220px;
}

.status-count {
  min-width: 34px;
  min-height: 25px;
  justify-content: center;
  font-size: 13px;
}

.progress-cell {
  display: grid;
  grid-template-columns: minmax(80px, 1fr) 42px;
  align-items: center;
  gap: 8px;
  min-width: 140px;
}

.detail-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.teacher-detail-dialog { display: flex; height: 100vh; flex-direction: column; background: #f4f7fa; }
.teacher-detail-toolbar { min-height: 70px; flex: 0 0 auto; padding: 8px 20px; background: #fff; }
.teacher-detail-content { min-height: 0; flex: 1 1 auto; overflow: auto; padding: 20px; }

.tracking-metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin: 18px 0;
}

.tracking-metric {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid #d9e2ec;
  border-radius: 8px;
  background: #fff;
}

.tracking-metric .q-icon { font-size: 28px; }
.tracking-metric div { display: flex; flex-direction: column; }
.tracking-metric strong { color: #102a43; font-size: 22px; line-height: 1.1; }
.tracking-metric span { color: #627d98; font-size: 12px; }

.detail-toolbar {
  margin-bottom: 14px;
}

.detail-toolbar .q-field {
  width: min(340px, 100%);
}

.context-label,
.requirement-name {
  max-width: 360px;
  white-space: normal;
  color: #102a43;
  font-weight: 600;
}

.requirement-code {
  color: #075985;
  font-size: 12px;
  font-weight: 800;
}

.file-summary {
  display: flex;
  align-items: center;
  gap: 9px;
  max-width: 330px;
  padding: 6px;
  border: 0;
  background: transparent;
  color: #102a43;
  text-align: left;
  cursor: pointer;
}

.file-summary:hover { background: #f0f6fb; }
.file-summary span { min-width: 0; }
.file-summary strong,
.file-summary small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-summary small { color: #829ab1; }
.empty-submission { display: flex; align-items: center; gap: 7px; color: #9fb3c8; }
.submissions-dialog { width: min(720px, 94vw); max-width: 720px; }

@media (max-width: 900px) {
  .tracking-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .detail-toolbar { align-items: stretch; flex-direction: column; }
  .detail-toolbar .q-btn-toggle,
  .detail-toolbar .q-field { width: 100%; }
}

@media (max-width: 520px) {
  .tracking-metrics { grid-template-columns: 1fr; }
  .teacher-detail-toolbar { padding: 8px 12px; }
  .teacher-detail-toolbar .teacher-identity { min-width: 0; }
  .teacher-detail-toolbar .panel-title { font-size: 15px; line-height: 1.25; }
  :deep(.detail-scope-toggle .q-btn__content span) { display: none; }
}
</style>
