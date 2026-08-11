<template>
  <q-page padding class="app-page exports-page">
    <div class="page-header">
      <div>
        <div class="page-kicker">Repositorio documental</div>
        <div class="page-title">Exportaciones</div>
        <div class="page-subtitle">Genera paquetes ZIP para revision, impresion y entrega de evidencias.</div>
      </div>
    </div>

    <q-card flat bordered class="data-panel">
      <q-card-section>
        <div class="text-h6">Filtros de exportacion</div>
        <div class="text-body2 text-grey-7 q-mb-md">Selecciona ciclo y programa antes de generar cualquier paquete.</div>
        <q-form @submit.prevent="generate">
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-4">
              <q-select v-model="form.accreditation_model_id" :options="modelOptions" label="Modelo" outlined dense emit-value map-options />
            </div>
            <div class="col-12 col-md-4">
              <q-select v-model="form.accreditation_cycle_id" :options="cycleOptions" label="Ciclo" outlined dense emit-value map-options />
            </div>
            <div class="col-12 col-md-4">
              <q-select v-model="form.program_id" :options="programOptions" label="Programa" outlined dense emit-value map-options />
            </div>
            <div class="col-12">
              <q-select v-model="form.statuses" :options="statusOptions" label="Estados" outlined dense multiple use-chips emit-value map-options />
            </div>
          </div>

          <div class="row q-col-gutter-md q-mt-md">
            <div class="col-12 col-md-6">
              <q-card flat bordered class="export-action-card">
                <q-card-section>
                  <q-icon name="inventory_2" color="primary" size="32px" />
                  <div>
                    <div class="text-subtitle1 text-weight-bold">ZIP de evidencias validadas</div>
                    <div class="text-body2 text-grey-7">Agrupa evidencias por criterio y requerimiento.</div>
                  </div>
                </q-card-section>
                <q-card-actions>
                  <q-btn color="primary" icon="archive" label="Generar ZIP" type="submit" :loading="loading" unelevated />
                </q-card-actions>
              </q-card>
            </div>
            <div class="col-12 col-md-6">
              <q-card flat bordered class="export-action-card">
                <q-card-section>
                  <q-icon name="print" color="secondary" size="32px" />
                  <div>
                    <div class="text-subtitle1 text-weight-bold">ZIP de silabos</div>
                    <div class="text-body2 text-grey-7">Descarga todos los silabos C5-PORT-01 sin entrar curso por curso.</div>
                  </div>
                </q-card-section>
                <q-card-actions>
                  <q-btn color="secondary" icon="folder_zip" label="Generar silabos" :loading="syllabiLoading" unelevated @click="generateSyllabi" />
                </q-card-actions>
              </q-card>
            </div>
          </div>
        </q-form>
      </q-card-section>
    </q-card>

    <q-card v-if="result" flat bordered class="q-mt-md data-panel">
      <q-card-section>
        <div class="text-subtitle1">ZIP generado</div>
        <div class="text-caption">Ruta: {{ result.path }}</div>
        <div class="text-caption">Estado: {{ result.status }}</div>
        <div v-if="result.stats" class="text-caption">Archivos: {{ result.stats.total_files }}</div>
      </q-card-section>
      <q-card-actions>
        <q-btn color="primary" icon="download" label="Descargar" :loading="downloadLoading === result.id" @click="downloadExport(result)" />
      </q-card-actions>
    </q-card>

    <q-card v-if="syllabiResult" flat bordered class="q-mt-md data-panel">
      <q-card-section>
        <div class="text-subtitle1">ZIP de silabos generado</div>
        <div class="text-caption">Ruta: {{ syllabiResult.path }}</div>
        <div class="text-caption">Estado: {{ syllabiResult.status }}</div>
        <div v-if="syllabiResult.stats" class="text-caption">Silabos incluidos: {{ syllabiResult.stats.total_files }}</div>
      </q-card-section>
      <q-card-actions>
        <q-btn color="secondary" icon="download" label="Descargar silabos" :loading="downloadLoading === syllabiResult.id" @click="downloadExport(syllabiResult)" />
      </q-card-actions>
    </q-card>
  </q-page>
</template>

<script>
export default {
  name: 'ExportPage',

  data () {
    return {
      loading: false,
      models: [],
      cycles: [],
      programs: [],
      result: null,
      syllabiResult: null,
      syllabiLoading: false,
      downloadLoading: null,
      form: {
        accreditation_model_id: null,
        accreditation_cycle_id: null,
        program_id: null,
        statuses: ['validated', 'approved']
      },
      statusOptions: [
        { label: 'Validadas', value: 'validated' },
        { label: 'Aprobadas', value: 'approved' },
        { label: 'Listas para exportar', value: 'ready_to_export' }
      ]
    }
  },

  computed: {
    modelOptions () {
      return this.models.map(item => ({ label: item.name, value: item.id }))
    },
    cycleOptions () {
      return this.cycles.map(item => ({ label: item.name, value: item.id }))
    },
    programOptions () {
      return this.programs.map(item => ({ label: item.name, value: item.id }))
    }
  },

  created () {
    this.loadCatalogs()
  },

  methods: {
    async loadCatalogs () {
      try {
        const [models, cycles, programs] = await Promise.all([
          this.$api.get('/accreditation-models'),
          this.$api.get('/accreditation-cycles'),
          this.$api.get('/programs')
        ])
        this.models = models.data
        this.cycles = cycles.data
        this.programs = programs.data
        if (this.models.length) this.form.accreditation_model_id = this.models[0].id
        if (this.cycles.length) this.form.accreditation_cycle_id = this.cycles[0].id
        if (this.programs.length) this.form.program_id = this.programs[0].id
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudieron cargar los catalogos' })
      }
    },

    async generate () {
      this.loading = true
      this.result = null
      try {
        const response = await this.$api.post('/exports/evidences-zip', this.form)
        this.result = response.data.data
        this.$q.notify({ type: 'positive', message: 'Exportacion generada' })
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo generar la exportacion' })
      } finally {
        this.loading = false
      }
    },

    async generateSyllabi () {
      this.syllabiLoading = true
      this.syllabiResult = null
      try {
        const response = await this.$api.post('/exports/syllabi-zip', {
          accreditation_cycle_id: this.form.accreditation_cycle_id,
          program_id: this.form.program_id,
          requirement_code: 'C5-PORT-01'
        })
        this.syllabiResult = response.data.data
        this.$q.notify({ type: 'positive', message: 'ZIP de silabos generado' })
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo generar el ZIP de silabos' })
      } finally {
        this.syllabiLoading = false
      }
    },

    async downloadExport (job) {
      if (!job?.id) return

      this.downloadLoading = job.id
      try {
        const response = await this.$api.get(`/exports/${job.id}/download`, { responseType: 'blob' })
        const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/zip' })
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = this.fileNameFromPath(job.path)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo descargar la exportacion' })
      } finally {
        this.downloadLoading = null
      }
    },

    fileNameFromPath (path) {
      return String(path || 'exportacion.zip').split('/').pop() || 'exportacion.zip'
    }
  }
}
</script>

<style scoped>
.export-action-card {
  height: 100%;
}

.export-action-card .q-card__section {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}
</style>
