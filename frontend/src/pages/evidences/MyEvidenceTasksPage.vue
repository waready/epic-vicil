<template>
  <q-page padding class="my-evidence-page">
    <!-- Encabezado -->
    <section class="page-hero">
      <div class="page-hero__content">
        <q-avatar class="page-hero__icon" size="56px">
          <q-icon name="folder_open" size="30px" />
        </q-avatar>

        <div>
          <div class="page-kicker">PORTAFOLIO DOCENTE</div>
          <h1 class="page-title">Mis evidencias</h1>
          <p class="page-subtitle">
            Administra los sílabos, exámenes, prácticas, rúbricas, materiales
            y evidencias de assessment de tus cursos asignados.
          </p>
        </div>
      </div>

      <div class="page-hero__actions">
        <q-btn
          outline
          color="primary"
          icon="refresh"
          label="Actualizar"
          no-caps
          :loading="loading"
          @click="loadTasks"
        />
      </div>
    </section>

    <!-- Resumen -->
    <section class="summary-grid">
      <article class="summary-card summary-card--blue">
        <div class="summary-card__icon">
          <q-icon name="menu_book" />
        </div>
        <div class="summary-card__content">
          <span class="summary-card__value">{{ regularCourseCount }}</span>
          <span class="summary-card__label">Cursos regulares</span>
        </div>
      </article>

      <article class="summary-card summary-card--orange">
        <div class="summary-card__icon">
          <q-icon name="fact_check" />
        </div>
        <div class="summary-card__content">
          <span class="summary-card__value">{{ assessmentCourseCount }}</span>
          <span class="summary-card__label">Cursos de medición</span>
        </div>
      </article>

      <article class="summary-card summary-card--amber">
        <div class="summary-card__icon">
          <q-icon name="pending_actions" />
        </div>
        <div class="summary-card__content">
          <span class="summary-card__value">{{ pendingTasksCount }}</span>
          <span class="summary-card__label">Evidencias pendientes</span>
        </div>
      </article>

      <article class="summary-card summary-card--green">
        <div class="summary-card__progress">
          <q-circular-progress
            show-value
            font-size="13px"
            :value="completionPercentage"
            size="52px"
            :thickness="0.16"
            color="positive"
            track-color="green-1"
          >
            {{ completionPercentage }}%
          </q-circular-progress>
        </div>
        <div class="summary-card__content">
          <span class="summary-card__value">
            {{ completedTasksCount }}/{{ tasks.length }}
          </span>
          <span class="summary-card__label">Avance general</span>
        </div>
      </article>
    </section>

    <!-- Área principal -->
    <section class="workspace-card">
      <header class="workspace-header">
        <div>
          <h2 class="workspace-title">Evidencias asignadas</h2>
          <p class="workspace-subtitle">
            {{ filteredTasks.length }} registro{{ filteredTasks.length === 1 ? '' : 's' }}
            encontrado{{ filteredTasks.length === 1 ? '' : 's' }}
          </p>
        </div>

        <q-btn-toggle
          v-model="viewMode"
          class="view-toggle"
          unelevated
          no-caps
          toggle-color="primary"
          color="grey-2"
          text-color="grey-8"
          :options="[
            { label: 'Por curso', value: 'grouped', icon: 'view_agenda' },
            { label: 'Tabla', value: 'table', icon: 'table_rows' }
          ]"
        />
      </header>

      <q-separator />

      <!-- Filtros -->
      <div class="filters-panel">
        <div class="row q-col-gutter-md items-center">
          <div class="col-12 col-md-4">
            <q-input
              v-model="filter"
              outlined
              dense
              clearable
              debounce="250"
              label="Buscar curso, código o evidencia"
              bg-color="white"
            >
              <template #prepend>
                <q-icon name="search" color="grey-6" />
              </template>
            </q-input>
          </div>

          <div class="col-12 col-sm-6 col-md-3">
            <q-select
              v-model="filters.status"
              :options="statusOptions"
              outlined
              dense
              clearable
              emit-value
              map-options
              label="Estado"
              bg-color="white"
              @update:model-value="loadTasks"
            >
              <template #prepend>
                <q-icon name="filter_alt" color="grey-6" />
              </template>
            </q-select>
          </div>

          <div class="col-12 col-sm-6 col-md-3">
            <q-select
              v-model="filters.group"
              :options="groupOptions"
              outlined
              dense
              clearable
              emit-value
              map-options
              label="Curso o medición"
              bg-color="white"
            >
              <template #prepend>
                <q-icon name="school" color="grey-6" />
              </template>
            </q-select>
          </div>

          <div class="col-12 col-md-2">
            <q-btn
              class="full-width"
              flat
              no-caps
              icon="filter_alt_off"
              label="Limpiar"
              color="grey-8"
              :disable="!hasActiveFilters"
              @click="clearFilters"
            />
          </div>
        </div>
      </div>

      <q-linear-progress
        v-if="loading"
        indeterminate
        color="primary"
        class="workspace-progress"
      />

      <!-- Vista tabla -->
      <q-table
        v-if="viewMode === 'table'"
        v-model:pagination="pagination"
        class="professional-table"
        :rows="filteredTasks"
        :columns="columns"
        row-key="id"
        flat
        separator="horizontal"
        :loading="loading"
        :rows-per-page-options="[10, 20, 50, 0]"
        :grid="$q.screen.lt.md"
        :hide-header="$q.screen.lt.md"
      >
        <template #header="props">
          <q-tr :props="props" class="table-header-row">
            <q-th
              v-for="col in props.cols"
              :key="col.name"
              :props="props"
              :class="[
                `table-header--${col.name}`,
                { 'text-center': ['status', 'actions'].includes(col.name) }
              ]"
            >
              {{ col.label }}
            </q-th>
          </q-tr>
        </template>

        <template #body="props">
          <q-tr
            :props="props"
            class="table-body-row"
            :class="rowClass(props.row)"
          >
            <q-td key="context" :props="props">
              <div class="course-cell">
                <q-avatar
                  rounded
                  size="42px"
                  :class="`course-avatar course-avatar--${taskGroupMeta(props.row).kind}`"
                >
                  <q-icon :name="taskGroupMeta(props.row).icon" size="22px" />
                </q-avatar>

                <div class="course-cell__text">
                  <div class="course-cell__title">
                    {{ taskGroupMeta(props.row).title }}
                  </div>
                  <div class="course-cell__subtitle">
                    {{ taskGroupMeta(props.row).subtitle }}
                  </div>
                </div>
              </div>
            </q-td>

            <q-td key="requirement" :props="props">
              <div class="requirement-cell">
                <div class="requirement-cell__code">
                  {{ props.row.requirement?.code || 'EVID' }}
                </div>
                <div class="requirement-cell__name">
                  {{ props.row.requirement?.name || 'Evidencia requerida' }}
                </div>
                <div class="requirement-cell__criterion">
                  {{ criterionLabel(props.row) }}
                </div>
              </div>
            </q-td>

            <q-td key="status" :props="props" class="text-center">
              <q-chip
                dense
                square
                :icon="statusIcon(props.row.status)"
                :color="statusColor(props.row.status)"
                text-color="white"
                class="status-chip"
              >
                {{ statusLabel(props.row.status) }}
              </q-chip>
            </q-td>

            <q-td key="current_submission" :props="props">
              <div
                v-if="props.row.current_submission?.id"
                class="submission-cell"
              >
                <div class="submission-cell__title">
                  {{ props.row.current_submission.title }}
                </div>
                <div class="submission-cell__meta">
                  <q-icon name="history" size="15px" />
                  Versión {{ props.row.current_submission.version_number || 1 }}
                  <span class="submission-cell__separator">•</span>
                  {{ formatSubmissionDate(props.row.current_submission) }}
                </div>
              </div>

              <div v-else class="no-submission">
                <q-icon name="cloud_off" size="17px" />
                Sin envío
              </div>
            </q-td>

            <q-td key="actions" :props="props" class="text-center">
              <div class="table-actions">
                <q-btn
                  unelevated
                  no-caps
                  size="sm"
                  color="primary"
                  icon="cloud_upload"
                  label=""
                  @click="openUpload(props.row)"
                />

                <q-btn
                  v-if="props.row.current_submission?.id"
                  outline
                  no-caps
                  size="sm"
                  color="primary"
                  icon="visibility"
                  label="Ver"
                  :to="`/evidences/${props.row.current_submission.id}`"
                />
              </div>
            </q-td>
          </q-tr>
        </template>

        <!-- Vista móvil -->
        <template #item="props">
          <div class="q-pa-sm col-12">
            <q-card flat bordered class="mobile-task-card">
              <q-card-section class="mobile-task-card__header">
                <div class="row items-start no-wrap">
                  <q-avatar
                    rounded
                    size="42px"
                    :class="`course-avatar course-avatar--${taskGroupMeta(props.row).kind}`"
                  >
                    <q-icon :name="taskGroupMeta(props.row).icon" size="22px" />
                  </q-avatar>

                  <div class="col q-ml-md">
                    <div class="mobile-task-card__course">
                      {{ taskGroupMeta(props.row).title }}
                    </div>
                    <div class="mobile-task-card__context">
                      {{ taskGroupMeta(props.row).subtitle }}
                    </div>
                  </div>
                </div>
              </q-card-section>

              <q-separator />

              <q-card-section>
                <div class="requirement-cell__code">
                  {{ props.row.requirement?.code || 'EVID' }}
                </div>
                <div class="requirement-cell__name q-mt-xs">
                  {{ props.row.requirement?.name || 'Evidencia requerida' }}
                </div>
                <div class="requirement-cell__criterion q-mt-xs">
                  {{ criterionLabel(props.row) }}
                </div>

                <div class="q-mt-md">
                  <q-chip
                    dense
                    square
                    :icon="statusIcon(props.row.status)"
                    :color="statusColor(props.row.status)"
                    text-color="white"
                    class="status-chip"
                  >
                    {{ statusLabel(props.row.status) }}
                  </q-chip>
                </div>

                <div
                  v-if="props.row.current_submission?.id"
                  class="mobile-submission q-mt-md"
                >
                  <div class="submission-cell__title">
                    {{ props.row.current_submission.title }}
                  </div>
                  <div class="submission-cell__meta">
                    Versión {{ props.row.current_submission.version_number || 1 }}
                    • {{ formatSubmissionDate(props.row.current_submission) }}
                  </div>
                </div>
              </q-card-section>

              <q-card-actions align="right" class="q-px-md q-pb-md">
                <q-btn
                  unelevated
                  no-caps
                  color="primary"
                  icon="cloud_upload"
                  label="Subir evidencia"
                  @click="openUpload(props.row)"
                />
                <q-btn
                  v-if="props.row.current_submission?.id"
                  flat
                  no-caps
                  color="primary"
                  icon="visibility"
                  label="Ver"
                  :to="`/evidences/${props.row.current_submission.id}`"
                />
              </q-card-actions>
            </q-card>
          </div>
        </template>

        <template #no-data>
          <div class="empty-table-state">
            <q-icon name="folder_off" size="52px" />
            <div class="empty-table-state__title">No se encontraron evidencias</div>
            <div class="empty-table-state__text">
              Prueba modificando los filtros o actualizando la información.
            </div>
          </div>
        </template>

        <template #loading>
          <q-inner-loading showing color="primary">
            <q-spinner-dots color="primary" size="48px" />
          </q-inner-loading>
        </template>
      </q-table>

      <!-- Vista agrupada -->
      <div v-else class="grouped-view">
        <q-banner
          v-if="!loading && groupedTasks.length === 0"
          class="empty-banner"
          rounded
        >
          <template #avatar>
            <q-icon name="folder_off" color="grey-6" />
          </template>
          No hay evidencias con los filtros seleccionados.
        </q-banner>

        <q-expansion-item
          v-for="group in groupedTasks"
          :key="group.key"
          default-opened
          expand-separator
          class="course-group-card"
          header-class="course-group-card__header"
        >
          <template #header>
            <q-item-section avatar>
              <q-avatar
                rounded
                :class="`course-avatar course-avatar--${group.kind}`"
              >
                <q-icon :name="group.icon" />
              </q-avatar>
            </q-item-section>

            <q-item-section>
              <q-item-label class="group-title">
                {{ group.title }}
              </q-item-label>
              <q-item-label caption class="group-subtitle">
                {{ group.subtitle }}
              </q-item-label>
            </q-item-section>

            <q-item-section side>
              <div class="group-summary">
                <q-chip dense square color="grey-2" text-color="grey-8">
                  {{ group.tasks.length }} evidencias
                </q-chip>
                <q-chip
                  dense
                  square
                  :color="group.pending > 0 ? 'orange-7' : 'positive'"
                  text-color="white"
                >
                  {{ group.pending }} pendientes
                </q-chip>
              </div>
            </q-item-section>
          </template>

          <q-markup-table flat separator="horizontal" class="group-task-table">
            <thead>
              <tr>
                <th class="text-left">Evidencia requerida</th>
                <th class="text-center group-col-status">Estado</th>
                <th class="text-left group-col-submission">Envios</th>
                <th class="text-center group-col-actions">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="task in group.tasks"
                :key="task.id"
                :class="rowClass(task)"
              >
                <td>
                  <div class="group-task-code">
                    {{ task.requirement?.code || 'EVID' }}
                  </div>
                  <div class="group-task-name">
                    {{ task.requirement?.name || 'Evidencia requerida' }}
                  </div>
                  <div class="group-task-criterion">
                    {{ criterionLabel(task) }}
                  </div>
                </td>
                <td class="text-center">
                  <q-chip
                    dense
                    square
                    :icon="statusIcon(task.status)"
                    :color="statusColor(task.status)"
                    text-color="white"
                    class="status-chip status-chip--compact"
                  >
                    {{ statusLabel(task.status) }}
                  </q-chip>
                </td>
                <td>
                  <div
                    v-if="task.current_submission?.id"
                    class="submission-cell submission-cell--compact"
                  >
                    <div class="submission-cell__title">
                      {{ task.current_submission.title }}
                    </div>
                    <div class="submission-cell__meta">
                      V{{ task.current_submission.version_number || 1 }}
                      <span class="submission-cell__separator">-</span>
                      {{ formatSubmissionDate(task.current_submission) }}
                    </div>
                  </div>
                  <div v-else class="no-submission no-submission--compact">
                    <q-icon name="cloud_off" size="16px" />
                    Sin envio
                  </div>
                </td>
                <td class="text-center">
                  <div class="group-task-actions">
                    <q-btn
                      unelevated
                      no-caps
                      dense
                      color="primary"
                      icon="cloud_upload"
                      label="Subir"
                      class="action-btn"
                      @click="openUpload(task)"
                    />

                    <q-btn
                      v-if="task.current_submission?.id"
                      flat
                      round
                      dense
                      color="primary"
                      icon="visibility"
                      :to="`/evidences/${task.current_submission.id}`"
                    >
                      <q-tooltip>Ver evidencia</q-tooltip>
                    </q-btn>
                  </div>
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </q-expansion-item>
      </div>
    </section>

    <!-- Diálogo de carga -->
    <q-dialog v-model="uploadDialog" persistent>
      <q-card class="upload-dialog">
        <q-form @submit.prevent="submitTask">
          <q-card-section class="upload-dialog__header">
            <div class="row items-start no-wrap">
              <q-avatar color="blue-1" text-color="primary" icon="cloud_upload" />
              <div class="q-ml-md col">
                <div class="upload-dialog__title">Subir evidencia</div>
                <div class="upload-dialog__subtitle">
                  {{ selectedTaskLabel }}
                </div>
              </div>
              <q-btn
                flat
                round
                dense
                icon="close"
                color="grey-7"
                v-close-popup
              />
            </div>
          </q-card-section>

          <q-separator />

          <q-card-section class="q-pa-lg">
            <q-banner class="upload-info-banner" rounded>
              <template #avatar>
                <q-icon name="info" color="primary" />
              </template>
              Usa nombres claros y evita subir copias duplicadas. Para archivos
              pesados, espera a que la carga termine antes de cerrar esta ventana.
            </q-banner>

            <div class="q-mt-lg">
              <q-input
                v-model="uploadForm.title"
                outlined
                label="Título de la evidencia"
                :rules="[val => !!val || 'El título es obligatorio']"
              >
                <template #prepend>
                  <q-icon name="title" />
                </template>
              </q-input>
            </div>

            <div class="q-mt-sm">
              <q-input
                v-model="uploadForm.description"
                outlined
                type="textarea"
                autogrow
                label="Descripción u observaciones"
              >
                <template #prepend>
                  <q-icon name="notes" />
                </template>
              </q-input>
            </div>

            <div class="q-mt-sm">
              <q-file
                v-model="uploadForm.files"
                outlined
                clearable
                counter
                multiple
                use-chips
                label="Seleccionar archivos"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.mp4,.zip"
                :rules="[
                  val => (val && val.length > 0) || 'Selecciona al menos un archivo'
                ]"
                class="professional-file-input"
              >
                <template #prepend>
                  <q-icon name="attach_file" />
                </template>
                <template #hint>
                  PDF, Office, imágenes, MP4 o ZIP
                </template>
              </q-file>
              <div v-if="directUploading" class="q-mt-md">
                <div class="row items-center justify-between q-mb-xs">
                  <span class="text-caption text-grey-7">Subiendo directo al almacenamiento externo</span>
                  <span class="text-caption text-weight-bold">{{ uploadProgress }}%</span>
                </div>
                <q-linear-progress :value="uploadProgress / 100" color="primary" rounded />
              </div>
            </div>
          </q-card-section>

          <q-separator />

          <q-card-actions align="right" class="q-pa-md">
            <q-btn
              flat
              no-caps
              label="Cancelar"
              color="grey-8"
              v-close-popup
            />
            <q-btn
              unelevated
              no-caps
              color="primary"
              icon="cloud_upload"
              label="Enviar evidencia"
              type="submit"
              :loading="saving"
            />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
export default {
  name: 'MyEvidenceTasksPage',

  data () {
    return {
      loading: false,
      saving: false,
      directUploading: false,
      uploadProgress: 0,
      filter: '',
      viewMode: 'grouped',
      uploadDialog: false,
      selectedTask: null,
      tasks: [],
      filters: {
        status: '',
        group: ''
      },
      uploadForm: {
        title: '',
        description: '',
        files: []
      },
      pagination: {
        page: 1,
        rowsPerPage: 10,
        sortBy: 'context',
        descending: false
      },
      statusOptions: [
        { label: 'Pendiente', value: 'pending' },
        { label: 'Asignado', value: 'assigned' },
        { label: 'Subido', value: 'uploaded' },
        { label: 'En revisión', value: 'under_review' },
        { label: 'Observado', value: 'observed' },
        { label: 'Corregido', value: 'corrected' },
        { label: 'Validado', value: 'validated' },
        { label: 'Aprobado', value: 'approved' },
        { label: 'Listo para exportar', value: 'ready_to_export' }
      ],
      columns: [
        {
          name: 'context',
          label: 'Curso / asignación',
          field: row => row.context_label || '',
          align: 'left',
          sortable: true,
          style: 'width: 42%',
          headerStyle: 'width: 42%'
        },
        {
          name: 'requirement',
          label: 'Evidencia requerida',
          field: row => row.requirement
            ? `${row.requirement.code || ''} ${row.requirement.name || ''}`
            : '',
          align: 'left',
          sortable: true,
          style: 'width: 30%',
          headerStyle: 'width: 30%'
        },
        {
          name: 'status',
          label: 'Estado',
          field: 'status',
          align: 'center',
          sortable: true,
          style: 'width: 10%',
          headerStyle: 'width: 10%'
        },
        {
          name: 'current_submission',
          label: 'Envíos',
          field: row => row.current_submission?.title || '',
          align: 'left',
          sortable: true,
          style: 'width: 10%',
          headerStyle: 'width: 10%'
        },
        {
          name: 'actions',
          label: 'Acciones',
          field: 'actions',
          align: 'center',
          sortable: false,
          style: 'width: 8%',
          headerStyle: 'width: 8%'
        }
      ]
    }
  },

  computed: {
    selectedTaskLabel () {
      if (!this.selectedTask) return ''

      const requirement = this.selectedTask.requirement
        ? `${this.selectedTask.requirement.code || ''} - ${this.selectedTask.requirement.name || ''}`
        : 'Evidencia'

      return `${this.taskGroupMeta(this.selectedTask).title} / ${requirement}`
    },

    filteredTasks () {
      const term = (this.filter || '').toLowerCase().trim()

      return this.tasks.filter(task => {
        if (
          this.filters.group &&
          this.taskGroupMeta(task).key !== this.filters.group
        ) {
          return false
        }

        if (!term) return true

        const meta = this.taskGroupMeta(task)

        return [
          meta.title,
          meta.subtitle,
          task.context_label,
          task.requirement?.code,
          task.requirement?.name,
          task.criterion?.code,
          task.criterion?.name,
          task.subcriterion?.code,
          task.subcriterion?.name,
          task.current_submission?.title
        ]
          .filter(Boolean)
          .join(' ')
          .toLowerCase()
          .includes(term)
      })
    },

    groupedTasks () {
      const groups = new Map()

      this.filteredTasks.forEach(task => {
        const meta = this.taskGroupMeta(task)

        if (!groups.has(meta.key)) {
          groups.set(meta.key, {
            ...meta,
            tasks: [],
            pending: 0
          })
        }

        const group = groups.get(meta.key)
        group.tasks.push(task)

        if (this.isPendingStatus(task.status)) {
          group.pending += 1
        }
      })

      return Array.from(groups.values()).sort((a, b) =>
        a.sort.localeCompare(b.sort)
      )
    },

    groupOptions () {
      const options = new Map()

      this.tasks.forEach(task => {
        const meta = this.taskGroupMeta(task)
        options.set(meta.key, {
          label: meta.title,
          value: meta.key
        })
      })

      return Array.from(options.values()).sort((a, b) =>
        a.label.localeCompare(b.label)
      )
    },

    regularCourseCount () {
      return this.uniqueGroupCount('course')
    },

    assessmentCourseCount () {
      return this.uniqueGroupCount('assessment')
    },

    pendingTasksCount () {
      return this.tasks.filter(task => this.isPendingStatus(task.status)).length
    },

    completedTasksCount () {
      const completedStatuses = [
        'uploaded',
        'under_review',
        'corrected',
        'validated',
        'approved',
        'ready_to_export'
      ]

      return this.tasks.filter(task =>
        completedStatuses.includes(task.status)
      ).length
    },

    completionPercentage () {
      if (!this.tasks.length) return 0

      return Math.round(
        (this.completedTasksCount / this.tasks.length) * 100
      )
    },

    hasActiveFilters () {
      return Boolean(
        this.filter ||
        this.filters.status ||
        this.filters.group
      )
    }
  },

  created () {
    this.loadTasks()
  },

  methods: {
    async loadTasks () {
      this.loading = true

      try {
        const params = { per_page: 200 }

        if (this.filters.status) {
          params.status = this.filters.status
        }

        const response = await this.$api.get('/my/evidence-tasks', { params })
        this.tasks = response.data.data || []
        this.pagination.page = 1
      } catch (error) {
        this.$q.notify({
          type: 'negative',
          icon: 'error',
          message: 'No se pudieron cargar tus evidencias'
        })
      } finally {
        this.loading = false
      }
    },

    clearFilters () {
      const mustReload = Boolean(this.filters.status)

      this.filter = ''
      this.filters.status = ''
      this.filters.group = ''
      this.pagination.page = 1

      if (mustReload) {
        this.loadTasks()
      }
    },

    taskGroupMeta (task) {
      if (task.context_type === 'teacher') {
        return {
          key: `teacher-${task.context_id}`,
          kind: 'teacher',
          title: 'Ficha docente y CV',
          subtitle:
            task.context_label ||
            'Datos académicos y documentación del docente',
          icon: 'badge',
          sort: 'z-teacher'
        }
      }

      const offering = task.course_offering || {}
      const course = offering.course || {}

      const courseLabel = course.code
        ? `${course.code} - ${course.name}`
        : (task.context_label || 'Curso asignado')

      if (
        task.context_type === 'assessment_course' ||
        offering.is_assessment_course
      ) {
        const resultCode = offering.assessment_result_code || 'RE'
        const resultName =
          offering.assessment_result_name || 'Resultado del estudiante'

        return {
          key: `assessment-${task.context_id}`,
          kind: 'assessment',
          title: `${resultCode} · ${courseLabel}`,
          subtitle: `${resultName}. Incluye los productos y los instrumentos usados en la medición.`,
          icon: 'fact_check',
          sort: `b-${resultCode}-${courseLabel}`
        }
      }

      return {
        key: `course-${task.context_id}`,
        kind: 'course',
        title: courseLabel,
        subtitle: task.context_label || 'Portafolio regular del curso',
        icon: 'menu_book',
        sort: `a-${courseLabel}`
      }
    },

    uniqueGroupCount (kind) {
      const keys = new Set()

      this.tasks.forEach(task => {
        const meta = this.taskGroupMeta(task)

        if (meta.kind === kind) {
          keys.add(meta.key)
        }
      })

      return keys.size
    },

    criterionLabel (task) {
      const criterion = task.criterion
        ? `${task.criterion.code || ''} - ${task.criterion.name || ''}`
        : 'Sin criterio asociado'

      const subcriterion = task.subcriterion?.code
        ? ` / ${task.subcriterion.code}`
        : ''

      return `${criterion}${subcriterion}`
    },

    isPendingStatus (status) {
      return ['pending', 'assigned', 'observed'].includes(status)
    },

    rowClass (task) {
      return {
        'table-body-row--observed': task.status === 'observed',
        'table-body-row--approved': [
          'validated',
          'approved',
          'ready_to_export'
        ].includes(task.status)
      }
    },

    openUpload (task) {
      this.selectedTask = task
      this.uploadForm = {
        title: task.requirement?.name || 'Evidencia',
        description: task.context_label
          ? `Contexto: ${task.context_label}`
          : '',
        files: []
      }
      this.uploadDialog = true
    },

    async submitTask () {
      if (
        !this.selectedTask ||
        !this.uploadForm.files ||
        this.uploadForm.files.length === 0
      ) {
        this.$q.notify({
          type: 'warning',
          message: 'Selecciona al menos un archivo'
        })
        return
      }

      this.saving = true
      this.uploadProgress = 0

      try {
        const files = Array.from(this.uploadForm.files)
        let response = null

        if (files.some(file => this.shouldUseDirectUpload(file))) {
          try {
            const fileAssetIds = []

            for (const file of files) {
              fileAssetIds.push(await this.uploadDirectFile(file))
            }

            response = await this.$api.post(
              `/evidence-tasks/${this.selectedTask.id}/submissions`,
              {
                title: this.uploadForm.title,
                description: this.uploadForm.description || '',
                file_asset_ids: fileAssetIds
              }
            )
          } catch (directError) {
            if (!this.canFallbackToServer(files, directError)) {
              throw directError
            }

            response = await this.submitTaskThroughServer(files)
          }
        } else {
          response = await this.submitTaskThroughServer(files)
        }

        this.$q.notify({
          type: 'positive',
          icon: 'check_circle',
          message: 'Evidencia enviada correctamente'
        })

        this.uploadDialog = false
        await this.loadTasks()

        if (response.data?.data?.id) {
          this.$router.push(`/evidences/${response.data.data.id}`)
        }
      } catch (error) {
        const message =
          error.response?.data?.message ||
          'No se pudo enviar la evidencia'

        this.$q.notify({
          type: 'negative',
          icon: 'error',
          message
        })
      } finally {
        this.saving = false
        this.directUploading = false
      }
    },

    async submitTaskThroughServer (files) {
      const data = new FormData()
      data.append('title', this.uploadForm.title)
      data.append('description', this.uploadForm.description || '')

      files.forEach(file => {
        data.append('files[]', file)
      })

      return this.$api.post(
        `/evidence-tasks/${this.selectedTask.id}/submissions`,
        data,
        {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        }
      )
    },

    shouldUseDirectUpload (file) {
      const thresholdBytes = 100 * 1024 * 1024
      return file.size >= thresholdBytes || (file.type || '').startsWith('video/')
    },

    canFallbackToServer (files, error) {
      const serverLimitBytes = 500 * 1024 * 1024
      const directUnavailable = [400, 404, 409, 422].includes(error.response?.status)
      return directUnavailable && files.every(file => file.size <= serverLimitBytes)
    },

    async uploadDirectFile (file) {
      this.directUploading = true
      this.uploadProgress = 0

      const presign = await this.$api.post('/uploads/direct/presign', {
        evidence_task_id: this.selectedTask.id,
        original_name: file.name,
        mime_type: file.type || 'application/octet-stream',
        size_bytes: file.size
      })

      await this.$axios.put(presign.data.upload_url, file, {
        headers: presign.data.headers || {},
        onUploadProgress: event => {
          if (event.total) {
            this.uploadProgress = Math.round((event.loaded / event.total) * 100)
          }
        }
      })

      const completed = await this.$api.post('/uploads/direct/complete', presign.data.file)
      this.uploadProgress = 100

      return completed.data.data.id
    },

    formatSubmissionDate (submission) {
      const value =
        submission.submitted_at ||
        submission.updated_at ||
        submission.created_at

      if (!value) return 'Fecha no disponible'

      const date = new Date(value)

      if (Number.isNaN(date.getTime())) {
        return 'Fecha no disponible'
      }

      return new Intl.DateTimeFormat('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      }).format(date)
    },

    statusLabel (status) {
      const labels = {
        pending: 'Pendiente',
        assigned: 'Asignado',
        uploaded: 'Subido',
        under_review: 'En revisión',
        observed: 'Observado',
        corrected: 'Corregido',
        validated: 'Validado',
        approved: 'Aprobado',
        ready_to_export: 'Listo para exportar'
      }

      return labels[status] || status || 'Pendiente'
    },

    statusColor (status) {
      const colors = {
        pending: 'orange-8',
        assigned: 'blue-7',
        uploaded: 'indigo-7',
        under_review: 'purple-7',
        observed: 'negative',
        corrected: 'cyan-8',
        validated: 'positive',
        approved: 'teal-8',
        ready_to_export: 'primary'
      }

      return colors[status] || 'grey-7'
    },

    statusIcon (status) {
      const icons = {
        pending: 'schedule',
        assigned: 'assignment_ind',
        uploaded: 'cloud_done',
        under_review: 'manage_search',
        observed: 'warning',
        corrected: 'published_with_changes',
        validated: 'verified',
        approved: 'check_circle',
        ready_to_export: 'inventory_2'
      }

      return icons[status] || 'help'
    }
  }
}
</script>

<style scoped>
.my-evidence-page {
  --page-bg: #f4f7fb;
  --card-border: #e4eaf1;
  --text-primary: #162033;
  --text-secondary: #64748b;
  --primary-dark: #0b4f7a;
  --shadow-soft: 0 12px 30px rgba(15, 23, 42, 0.06);

  min-height: 100%;
  background: var(--page-bg);
  color: var(--text-primary);
}

/* Encabezado */
.page-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
  padding: 24px;
  border: 1px solid var(--card-border);
  border-radius: 16px;
  background:
    linear-gradient(135deg, rgba(11, 79, 122, 0.06), rgba(255, 255, 255, 0) 58%),
    #fff;
  box-shadow: var(--shadow-soft);
}

.page-hero__content {
  display: flex;
  align-items: center;
  min-width: 0;
}

.page-hero__icon {
  flex: 0 0 auto;
  margin-right: 18px;
  background: linear-gradient(135deg, #0b4f7a, #1976d2);
  color: #fff;
  box-shadow: 0 10px 24px rgba(11, 79, 122, 0.22);
}

.page-kicker {
  margin-bottom: 4px;
  color: var(--primary-dark);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.12em;
}

.page-title {
  margin: 0;
  color: var(--text-primary);
  font-size: clamp(1.65rem, 3vw, 2.15rem);
  font-weight: 800;
  line-height: 1.15;
}

.page-subtitle {
  max-width: 820px;
  margin: 8px 0 0;
  color: var(--text-secondary);
  font-size: 0.95rem;
  line-height: 1.55;
}

/* Tarjetas de resumen */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.summary-card {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 0;
  padding: 18px;
  overflow: hidden;
  border: 1px solid var(--card-border);
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.045);
}

.summary-card::before {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  width: 4px;
  content: '';
}

.summary-card--blue::before {
  background: #1976d2;
}

.summary-card--orange::before {
  background: #ef6c00;
}

.summary-card--amber::before {
  background: #f9a825;
}

.summary-card--green::before {
  background: #2e7d32;
}

.summary-card__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  flex: 0 0 48px;
  margin-right: 14px;
  border-radius: 12px;
  font-size: 25px;
}

.summary-card--blue .summary-card__icon {
  background: #e8f2fc;
  color: #1565c0;
}

.summary-card--orange .summary-card__icon {
  background: #fff0e5;
  color: #e65100;
}

.summary-card--amber .summary-card__icon {
  background: #fff8df;
  color: #f57f17;
}

.summary-card__progress {
  margin-right: 14px;
}

.summary-card__content {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.summary-card__value {
  color: var(--text-primary);
  font-size: 1.45rem;
  font-weight: 800;
  line-height: 1.15;
}

.summary-card__label {
  margin-top: 4px;
  color: var(--text-secondary);
  font-size: 0.82rem;
  font-weight: 600;
}

/* Contenedor principal */
.workspace-card {
  overflow: hidden;
  border: 1px solid var(--card-border);
  border-radius: 16px;
  background: #fff;
  box-shadow: var(--shadow-soft);
}

.workspace-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 20px 22px;
}

.workspace-title {
  margin: 0;
  color: var(--text-primary);
  font-size: 1.12rem;
  font-weight: 800;
}

.workspace-subtitle {
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 0.82rem;
}

.view-toggle {
  min-width: 230px;
  border-radius: 9px;
}

.filters-panel {
  padding: 16px 20px;
  background: #f8fafc;
}

.workspace-progress {
  height: 3px;
}

/* Tabla */
.professional-table {
  min-height: 320px;
  border-radius: 0;
}

:deep(.professional-table .q-table__middle) {
  max-height: 680px;
}

:deep(.professional-table table) {
  table-layout: fixed;
}

:deep(.professional-table thead tr) {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #f8fafc;
}

:deep(.professional-table thead th) {
  height: 52px;
  padding: 0 18px;
  border-bottom: 1px solid #dfe6ee;
  color: #475569;
  font-size: 0.74rem;
  font-weight: 800;
  letter-spacing: 0.035em;
  text-transform: uppercase;
}

:deep(.professional-table tbody td) {
  padding: 15px 18px;
  border-color: #edf1f5;
  vertical-align: middle;
}

.table-body-row {
  transition:
    background-color 0.18s ease,
    box-shadow 0.18s ease;
}

.table-body-row:hover {
  background: #f8fbff;
}

.table-body-row--observed {
  box-shadow: inset 3px 0 0 #c62828;
}

.table-body-row--approved {
  box-shadow: inset 3px 0 0 #2e7d32;
}

.course-cell {
  display: flex;
  align-items: flex-start;
  min-width: 0;
}

.course-avatar {
  flex: 0 0 auto;
}

.course-avatar--course {
  background: #e8f2fc;
  color: #1565c0;
}

.course-avatar--assessment {
  background: #fff0e5;
  color: #e65100;
}

.course-avatar--teacher {
  background: #eee9ff;
  color: #5e35b1;
}

.course-cell__text {
  min-width: 0;
  margin-left: 12px;
}

.course-cell__title {
  display: -webkit-box;
  overflow: hidden;
  color: #1e293b;
  font-size: 0.91rem;
  font-weight: 750;
  line-height: 1.35;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
}

.course-cell__subtitle {
  display: -webkit-box;
  overflow: hidden;
  margin-top: 4px;
  color: #64748b;
  font-size: 0.76rem;
  line-height: 1.35;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.requirement-cell__code {
  color: var(--primary-dark);
  font-size: 0.72rem;
  font-weight: 900;
  letter-spacing: 0.035em;
  text-transform: uppercase;
}

.requirement-cell__name {
  margin-top: 3px;
  color: #1f2937;
  font-size: 0.88rem;
  font-weight: 700;
  line-height: 1.4;
  white-space: normal;
}

.requirement-cell__criterion {
  margin-top: 5px;
  color: #64748b;
  font-size: 0.74rem;
  line-height: 1.35;
  white-space: normal;
}

.status-chip {
  min-height: 26px;
  border-radius: 7px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.status-chip--compact {
  min-width: 92px;
  justify-content: center;
}

.submission-cell__title {
  display: -webkit-box;
  overflow: hidden;
  color: #334155;
  font-size: 0.84rem;
  font-weight: 700;
  line-height: 1.35;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.submission-cell__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 5px;
  color: #64748b;
  font-size: 0.73rem;
}

.submission-cell__separator {
  color: #cbd5e1;
}

.no-submission {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #94a3b8;
  font-size: 0.78rem;
}

.table-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 6px;
}

.empty-table-state {
  display: flex;
  width: 100%;
  min-height: 280px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  padding: 32px;
  color: #94a3b8;
  text-align: center;
}

.empty-table-state__title {
  margin-top: 12px;
  color: #475569;
  font-size: 1rem;
  font-weight: 800;
}

.empty-table-state__text {
  margin-top: 4px;
  font-size: 0.84rem;
}

/* Vista agrupada */
.grouped-view {
  display: grid;
  gap: 12px;
  padding: 18px;
  background: #f8fafc;
}

.course-group-card {
  overflow: hidden;
  border: 1px solid var(--card-border);
  border-radius: 12px;
  background: #fff;
}

:deep(.course-group-card__header) {
  min-height: 80px;
}

.group-title {
  white-space: normal;
  color: #1e293b;
  font-size: 0.93rem;
  font-weight: 800;
  line-height: 1.35;
}

.group-subtitle {
  white-space: normal;
  line-height: 1.35;
}

.group-summary {
  display: flex;
  align-items: flex-end;
  flex-direction: column;
  gap: 5px;
}

.group-task-table {
  border-top: 1px solid #e5ebf2;
  background: #fff;
}

:deep(.group-task-table table) {
  width: 100%;
  table-layout: fixed;
}

:deep(.group-task-table th) {
  height: 42px;
  padding: 0 14px;
  border-bottom: 1px solid #e5ebf2;
  background: #f9fbfd;
  color: #475569;
  font-size: 0.7rem;
  font-weight: 900;
  letter-spacing: 0.035em;
  text-transform: uppercase;
}

:deep(.group-task-table td) {
  padding: 13px 14px;
  border-bottom-color: #edf1f5;
  vertical-align: middle;
}

.group-col-status {
  width: 126px;
}

.group-col-submission {
  width: 168px;
}

.group-col-actions {
  width: 126px;
}

.group-task-code {
  color: var(--primary-dark);
  font-weight: 900;
  font-size: 0.72rem;
}

.group-task-name {
  margin-top: 3px;
  color: #1f2937;
  font-weight: 700;
  line-height: 1.35;
  white-space: normal;
}

.group-task-criterion {
  margin-top: 4px;
  color: #64748b;
  font-size: 0.72rem;
  line-height: 1.35;
  white-space: normal;
}

.group-task-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.action-btn {
  min-width: 76px;
}

.submission-cell--compact .submission-cell__title {
  -webkit-line-clamp: 1;
  font-size: 0.78rem;
}

.submission-cell--compact .submission-cell__meta,
.no-submission--compact {
  font-size: 0.72rem;
}

.no-submission--compact {
  white-space: nowrap;
}

.empty-banner {
  border: 1px dashed #cbd5e1;
  background: #fff;
  color: #64748b;
}

/* Diálogo */
.upload-dialog {
  width: 720px;
  max-width: 94vw;
  overflow: hidden;
  border-radius: 16px;
}

.upload-dialog__header {
  padding: 20px 22px;
  background: #f8fafc;
}

.upload-dialog__title {
  color: #172033;
  font-size: 1.15rem;
  font-weight: 800;
}

.upload-dialog__subtitle {
  margin-top: 4px;
  color: #64748b;
  font-size: 0.82rem;
  line-height: 1.4;
}

.upload-info-banner {
  border: 1px solid #dbeafe;
  background: #f4f9ff;
  color: #475569;
  font-size: 0.82rem;
}

:deep(.professional-file-input .q-field__control) {
  min-height: 78px;
}

/* Tarjeta móvil */
.mobile-task-card {
  overflow: hidden;
  border-radius: 12px;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.045);
}

.mobile-task-card__header {
  background: #f8fafc;
}

.mobile-task-card__course {
  color: #1e293b;
  font-size: 0.91rem;
  font-weight: 800;
  line-height: 1.35;
}

.mobile-task-card__context {
  margin-top: 4px;
  color: #64748b;
  font-size: 0.76rem;
  line-height: 1.35;
}

.mobile-submission {
  padding: 10px 12px;
  border: 1px solid #e5ebf2;
  border-radius: 9px;
  background: #f8fafc;
}

/* Responsive */
@media (max-width: 1199px) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .my-evidence-page {
    padding: 12px;
  }

  .page-hero {
    align-items: flex-start;
    flex-direction: column;
    padding: 18px;
  }

  .page-hero__content {
    align-items: flex-start;
  }

  .page-hero__icon {
    width: 48px;
    height: 48px;
    margin-right: 12px;
  }

  .page-hero__actions,
  .page-hero__actions .q-btn {
    width: 100%;
  }

  .summary-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .workspace-header {
    align-items: stretch;
    flex-direction: column;
    padding: 16px;
  }

  .view-toggle {
    width: 100%;
    min-width: 0;
  }

  .filters-panel {
    padding: 14px;
  }

  .group-summary {
    display: none;
  }

  .group-task-item {
    align-items: flex-start;
    flex-direction: column;
  }

  .group-task-actions {
    width: 100%;
    justify-content: flex-start;
    margin-top: 10px;
  }
}
</style>
