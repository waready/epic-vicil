<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">Exportaciones</div>

    <q-card>
      <q-card-section>
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

          <q-btn class="q-mt-lg" color="primary" icon="archive" label="Generar ZIP" type="submit" :loading="loading" />
        </q-form>
      </q-card-section>
    </q-card>

    <q-card v-if="result" class="q-mt-md">
      <q-card-section>
        <div class="text-subtitle1">ZIP generado</div>
        <div class="text-caption">Ruta: {{ result.path }}</div>
        <div class="text-caption">Estado: {{ result.status }}</div>
      </q-card-section>
      <q-card-actions v-if="downloadUrl">
        <q-btn color="primary" icon="download" label="Descargar" :href="downloadUrl" target="_blank" />
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
      downloadUrl: null,
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
      this.downloadUrl = null
      try {
        const response = await this.$api.post('/exports/evidences-zip', this.form)
        this.result = response.data.data
        this.downloadUrl = response.data.download_url
        this.$q.notify({ type: 'positive', message: 'Exportacion generada' })
      } catch (error) {
        this.$q.notify({ type: 'negative', message: 'No se pudo generar la exportacion' })
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
