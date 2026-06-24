<template>
  <q-page padding class="app-page">
    <div class="page-header">
      <div>
        <div class="page-kicker">Repositorio documental</div>
        <div class="page-title">Evidencias</div>
        <div class="page-subtitle">Repositorio de entregas, versiones y revision por criterio.</div>
      </div>
      <q-btn v-if="can('create.evidences')" color="primary" icon="add" label="Subir evidencia" unelevated to="/evidences/create" />
    </div>

    <q-card flat bordered class="q-mb-md filter-panel">
      <q-card-section>
        <div class="row q-col-gutter-md">
          <div class="col-12 col-md-3">
            <q-input v-model="filters.search" label="Buscar" outlined dense clearable @keyup.enter="applyFilters" />
          </div>
          <div class="col-12 col-md-2">
            <q-select v-model="filters.program_id" :options="programOptions" label="Programa" outlined dense clearable emit-value map-options />
          </div>
          <div class="col-12 col-md-2">
            <q-select v-model="filters.cycle_id" :options="cycleOptions" label="Ciclo" outlined dense clearable emit-value map-options />
          </div>
          <div class="col-12 col-md-2">
            <q-select v-model="filters.criterion_id" :options="criterionOptions" label="Criterio" outlined dense clearable emit-value map-options />
          </div>
          <div class="col-12 col-md-2">
            <q-select v-model="filters.status" :options="statusOptions" label="Estado" outlined dense clearable emit-value map-options />
          </div>
          <div class="col-12 col-md-1 flex items-center">
            <q-btn color="primary" icon="filter_alt" class="full-width" @click="applyFilters">
              <q-tooltip>Filtrar</q-tooltip>
            </q-btn>
          </div>
        </div>
      </q-card-section>
    </q-card>

    <q-table
      flat
      v-model:pagination="pagination"
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      :rows-per-page-options="[10, 15, 25, 50]"
      binary-state-sort
      @request="onTableRequest"
    >
      <template v-slot:body-cell-status="props">
        <q-td :props="props">
          <q-badge :color="statusColor(props.row.status)">{{ statusLabel(props.row.status) }}</q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props" class="q-gutter-xs">
          <q-btn dense flat round icon="visibility" color="primary" :to="`/evidences/${props.row.id}`">
            <q-tooltip>Ver detalle</q-tooltip>
          </q-btn>
          <q-btn v-if="can('create.evidences')" dense flat round icon="upload_file" color="secondary" @click="openVersionDialog(props.row)">
            <q-tooltip>Subir nueva version</q-tooltip>
          </q-btn>
          <q-btn v-if="can('review.evidences')" dense flat round icon="feedback" color="negative" @click="openReviewDialog(props.row, 'observe')">
            <q-tooltip>Observar</q-tooltip>
          </q-btn>
          <q-btn v-if="can('validate.evidences')" dense flat round icon="verified" color="positive" @click="openReviewDialog(props.row, 'validate')">
            <q-tooltip>Validar</q-tooltip>
          </q-btn>
          <q-btn v-if="can('approve.evidences')" dense flat round icon="task_alt" color="green" @click="openReviewDialog(props.row, 'approve')">
            <q-tooltip>Aprobar</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="reviewDialog">
      <q-card class="dialog-card">
        <q-card-section>
          <div class="text-h6">{{ reviewTitle }}</div>
        </q-card-section>
        <q-card-section>
          <q-input v-model="review.comment" label="Comentario" type="textarea" outlined />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" label="Guardar" :loading="saving" @click="submitReview" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="versionDialog">
      <q-card class="dialog-card">
        <q-card-section>
          <div class="text-h6">Nueva version</div>
        </q-card-section>
        <q-card-section>
          <q-input v-model="version.change_summary" label="Resumen de cambios" type="textarea" outlined class="q-mb-md" />
          <q-file v-model="version.file" label="Archivo" outlined clearable />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" label="Subir" :loading="saving" @click="submitVersion" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { getStoredUser } from 'src/utils/auth'

export default {
  name: 'EvidenceListPage',

  data () {
    return {
      loading: false,
      saving: false,
      rows: [],
      programs: [],
      cycles: [],
      criteria: [],
      filters: {
        search: '',
        program_id: null,
        cycle_id: null,
        criterion_id: null,
        status: null
      },
      pagination: {
        page: 1,
        rowsPerPage: 15,
        sortBy: 'updated_at',
        descending: true,
        rowsNumber: 0
      },
      reviewDialog: false,
      review: {
        evidence: null,
        action: null,
        comment: ''
      },
      versionDialog: false,
      version: {
        evidence: null,
        change_summary: '',
        file: null
      },
      statusOptions: [
        { label: 'Pendiente', value: 'pending' },
        { label: 'Subida', value: 'uploaded' },
        { label: 'En revision', value: 'in_review' },
        { label: 'Observada', value: 'observed' },
        { label: 'Corregida', value: 'corrected' },
        { label: 'Validada', value: 'validated' },
        { label: 'Aprobada', value: 'approved' },
        { label: 'Lista para exportar', value: 'ready_to_export' }
      ],
      columns: [
        { name: 'title', label: 'Titulo', field: 'title', align: 'left', sortable: true },
        { name: 'program', label: 'Programa', field: row => row.program ? row.program.name : '', align: 'left' },
        { name: 'cycle', label: 'Ciclo', field: row => row.cycle ? row.cycle.name : '', align: 'left' },
        { name: 'criterion', label: 'Criterio', field: row => row.criterion ? `${row.criterion.code} - ${row.criterion.name}` : '', align: 'left' },
        { name: 'requirement', label: 'Requerimiento', field: row => row.requirement ? row.requirement.name : '', align: 'left' },
        { name: 'responsible', label: 'Responsable', field: row => row.teacher ? `${row.teacher.first_name} ${row.teacher.last_name}` : (row.submitted_by ? row.submitted_by.name : ''), align: 'left' },
        { name: 'status', label: 'Estado', field: 'status', align: 'center' },
        { name: 'version_number', label: 'Version', field: 'version_number', align: 'center' },
        { name: 'submitted_at', label: 'Fecha', field: row => this.formatDate(row.submitted_at), align: 'left', sortable: true },
        { name: 'actions', label: 'Acciones', align: 'center' }
      ]
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

    reviewTitle () {
      const map = { observe: 'Observar evidencia', validate: 'Validar evidencia', approve: 'Aprobar evidencia' }
      return map[this.review.action] || 'Revision'
    },

    userPermissions () {
      const user = getStoredUser()
      return (user.permissions || []).map(item => item.name)
    }
  },

  created () {
    this.loadCatalogs()
    this.loadEvidences()
  },

  methods: {
    async loadCatalogs () {
      try {
        const [programs, cycles, criteria] = await Promise.all([
          this.$api.get('/programs'),
          this.$api.get('/accreditation-cycles'),
          this.$api.get('/accreditation-criteria')
        ])
        this.programs = programs.data
        this.cycles = cycles.data
        this.criteria = criteria.data
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los catalogos' })
      }
    },

    async loadEvidences (tableProps = null) {
      this.loading = true
      try {
        const nextPagination = tableProps && tableProps.pagination ? tableProps.pagination : this.pagination
        const params = {}
        Object.keys(this.filters).forEach(key => {
          if (this.filters[key]) params[key] = this.filters[key]
        })
        params.page = nextPagination.page || 1
        params.per_page = nextPagination.rowsPerPage || 15
        if (nextPagination.sortBy) {
          params.sort_by = nextPagination.sortBy
          params.descending = nextPagination.descending ? 1 : 0
        }
        const response = await this.$api.get('/evidences', { params })
        this.rows = response.data.data || []
        const meta = response.data.meta || {}
        this.pagination = {
          ...nextPagination,
          page: meta.current_page || nextPagination.page || 1,
          rowsPerPage: meta.per_page || nextPagination.rowsPerPage || 15,
          rowsNumber: meta.total || this.rows.length
        }
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar las evidencias' })
      } finally {
        this.loading = false
      }
    },

    onTableRequest (props) {
      this.loadEvidences(props)
    },

    applyFilters () {
      this.pagination.page = 1
      this.loadEvidences()
    },

    openReviewDialog (evidence, action) {
      this.review = { evidence, action, comment: '' }
      this.reviewDialog = true
    },

    async submitReview () {
      this.saving = true
      try {
        await this.$api.post(`/evidences/${this.review.evidence.id}/${this.review.action}`, { comment: this.review.comment })
        this.$q.notify({ type: 'positive', message: 'Estado actualizado' })
        this.reviewDialog = false
        this.loadEvidences()
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo actualizar la evidencia' })
      } finally {
        this.saving = false
      }
    },

    openVersionDialog (evidence) {
      this.version = { evidence, change_summary: '', file: null }
      this.versionDialog = true
    },

    async submitVersion () {
      if (!this.version.file) {
        this.$q.notify({ type: 'warning', message: 'Selecciona un archivo' })
        return
      }

      this.saving = true
      try {
        const data = new FormData()
        data.append('change_summary', this.version.change_summary || '')
        data.append('file', this.version.file)
        await this.$api.post(`/evidences/${this.version.evidence.id}/versions`, data, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        this.$q.notify({ type: 'positive', message: 'Version registrada' })
        this.versionDialog = false
        this.loadEvidences()
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo subir la version' })
      } finally {
        this.saving = false
      }
    },

    statusColor (status) {
      const map = {
        pending: 'grey',
        assigned: 'blue-grey',
        uploaded: 'blue',
        in_review: 'indigo',
        observed: 'negative',
        corrected: 'orange',
        validated: 'positive',
        approved: 'green',
        ready_to_export: 'teal'
      }
      return map[status] || 'grey'
    },

    statusLabel (status) {
      const found = this.statusOptions.find(item => item.value === status)
      return found ? found.label : status
    },

    formatDate (value) {
      return value ? new Date(value).toLocaleDateString() : ''
    },

    can (permission) {
      return this.userPermissions.includes(permission)
    }
  }
}
</script>
